<?php

/*
 * This file is part of Opus-fakturering.
 *
 * (c) 2017–2018 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Command;

use App\Entity\ProcessedInvoice;
use App\Service\HarvestApi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HarvestExportCommand extends BaseCommand
{
    /** @var HarvestApi */
    private $harvestApi;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var \Swift_Mailer */
    private $mailer;

    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    public function __construct(HarvestApi $harvestApi, EntityManagerInterface $entityManager, \Swift_Mailer $mailer)
    {
        parent::__construct();
        $this->harvestApi = $harvestApi;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
    }

    public function configure()
    {
        $this->setName('app:harvest:export')
            ->setDescription('Harvest export');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $invoices = $this->getInvoices();
        $data = $this->process($invoices);
        $csv = $this->format($data);
        $this->export($csv);
        $this->entityManager->flush();
    }

    private function getInvoices()
    {
        $result = $this->harvestApi->getInvoices([
            'state' => HarvestApi::INVOICE_STATE_DRAFT,
            'updated_since' => (new \DateTime('-1 month'))->format(\DateTime::ATOM),
        ]);

        // @FIXME: Exclude already processed invoices.

        return $result->invoices;
    }

    private function process(array $invoices)
    {
        $defaultValues = [
            'invoice' => [
                'salgsorganisation' => '0020',
                'salgskanal' => '10',
                'division' => '20',
                'ordreart' => 'ZIRA',
            ],
            'item' => [
                'psp_element' => 'xg-4301350000-00003',
                'materialenr' => '0087',
            ],
        ];

        $repository = $this->entityManager->getRepository(ProcessedInvoice::class);
        $type = 'harvest';

        $rows = [];
        foreach ($invoices as $invoice) {
            $identifier = $invoice->number;
            $this->output->writeln('Invoice '.$identifier);
            $existing = $repository->findOneBy(['type' => $type, 'identifier' => $identifier]);
            if ($existing !== null) {
                $this->output->writeln(sprintf('Invoice %s already processed. Skipping.', $identifier));
                continue;
            }
            $this->entityManager->persist(new ProcessedInvoice($type, $identifier));

            if (0 === count($invoice->line_items)) {
                continue;
            }

            $project = $this->harvestApi->getProject($invoice->line_items[0]->project->id);
            if (null === $project) {
                continue;
            }

            $payerId = $this->getPayerId($project);

            if (null === $payerId) {
                $this->output->writeln('<error>No payer id!</error>');
                $subject = sprintf('Missing payer id. Harvest invoice #%s', $identifier);
                $message = (new \Swift_Message($subject))
                    ->setFrom('send@example.com')
                    ->setTo('recipient@example.com')
                    ->setBody(json_encode($invoice, JSON_PRETTY_PRINT), 'text/plain')
                ;

                $this->mailer->send($message);
                continue;
            }

            $description = $this->getInvoiceDescription($project);

            $invoice = $this->merge($invoice, $defaultValues['invoice'] + ['ordregiver' => $payerId, 'toptekst' => $description]);

            $rows[] = [
                'H', // Linietype
                $invoice->ordregiver, // Ordregiver
                null, // Fakturamodtager
                $this->formatDate($invoice->issue_date), // Fakturadato
                $this->formatDate($invoice->issue_date), // Bilagsdato
                $invoice->salgsorganisation, // Salgsorganisation
                $invoice->salgskanal, // Salgskanal
                $invoice->division, // Division
                $invoice->ordreart, // Ordreart
                null, // Ekstern bilagsnr.
                null, // YdelsesModtagerNummerKode
                null, // Ydelsesmodtager
                null, // Kundens konto
                null, // Indkøbsordrenr.
                null, // Kunderef.ID
                $invoice->toptekst, // Toptekst
                null, // Leverandør
                null, // EAN nr.
                null, // Organisationsenhed
                null, // Kreditornummer
                null, // Områdenummer
                null, // Betalingsart
                null, // Reference
                null, // Stiftelsesdato
                null, // Periode fra
                null, // Periode til
                null, // Ændringsårsagskode
                null, // Ændringsårsagstekst
            ];

            foreach ($invoice->line_items as $item) {
                $item = $this->merge($item, $defaultValues['item']);

                $rows[] = [
                    'L', // Linietype
                    $item->materialenr, // Materiale (vare)nr.
                    null, // Beskrivelse
                    $item->quantity, // Ordremængde
                    $item->unit_price, // Beløb pr. enhed.
                    null, // Priser fra SAP
                    $item->psp_element, // PSP-element nr.
                    null, // YdelsesModtagerNummerKode
                    null, // Ydelsesmodtager
                    null, // Kundens konto
                    null, // Indkøbsordrenr.
                    null, // Tekst materialesalg
                    null, // Positionsnote
                ];
            }
        }

        return $rows;
    }

    private function getInvoiceDescription(object $project) {
        $description = $project->notes;

        // Strip out stuff.
        $description = preg_replace('@^(Filer:|https?://.+)@m', '', $description);

        // Normalize white-space.
        $description = trim($description);
        $description = preg_replace(['@[ ]+@', '@^[ ]+@', "@\r@", "@\n{2,}@"], [' ', '', '', "\n\n"], $description);

        // Trim to maximum allowed length.
        return substr($description, 0, 500);
    }

    private function merge(object $o, array $values = null)
    {
        return ($values != null) ? (object)array_merge($values, (array)$o) : $o;
    }

    private function formatDate($date)
    {
        $d = new \DateTime($date);
        return $d->format('d.m.Y');
    }

    private function getPayerId(object $project)
    {
        $notes = $project->notes;

        if (empty($notes)) {
            return null;
        }

        if (preg_match('@EAN-nummer: (?P<id>[0-9]{13})@', $notes, $matches)) {
            return $matches['id'];
        }
        if (preg_match('@Debitornummer: (?P<id>[0-9]{4})@', $notes, $matches)) {
            return $matches['id'];
        }

        return null;
    }

    private function format(array $data)
    {
        $handle = fopen('php://memory', 'w+b');

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);

        return stream_get_contents($handle);
    }

    private function export($data) {
        $this->output->write($data);

        $message = (new \Swift_Message('Hello Email'))
            ->setFrom('send@example.com')
            ->setTo('recipient@example.com')
            ->setBody($data, 'text/plain')
            ->attach(new \Swift_Attachment($data, 'stuff.csv', 'text/csv'));

        $this->mailer->send($message);
    }
}

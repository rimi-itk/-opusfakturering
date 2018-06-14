<?php

/*
 * This file is part of Opus-fakturering.
 *
 * (c) 2017–2018 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Service\Exporter;

use App\Entity\Account;
use App\Service\Exporter\Exception\InvalidAccountException;
use App\Service\Harvest\HarvestApi;

class HarvestExporter extends AbstractExporter
{
    public function validateAccount(Account $account)
    {
        throw new InvalidAccountException();
    }

    public function run()
    {
        header('Content-type: text/plain');
        echo var_export(null, true);
        die(__FILE__.':'.__LINE__.':'.__METHOD__);
        parent::run();
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
            'line' => [
                'psp_element' => 'xg-4301350000-00003',
                'materialenr' => '0087',
            ],
        ];

        $repository = $this->entityManager->getRepository(Invoice::class);
        $type = 'harvest';

        $rows = [];
        foreach ($invoices as $invoice) {
            $identifier = $invoice->number;
            $this->output->writeln('Invoice '.$identifier);
            $existing = $repository->findOneBy(['type' => $type, 'identifier' => $identifier]);
            if (null !== $existing) {
                $this->output->writeln(sprintf('Invoice %s already processed. Skipping.', $identifier));
                continue;
            }
            $this->entityManager->persist(new Invoice($type, $identifier));

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

            foreach ($invoice->line_items as $line) {
                $line = $this->merge($line, $defaultValues['line']);

                $rows[] = [
                    'L', // Linietype
                    $line->materialenr, // Materiale (vare)nr.
                    null, // Beskrivelse
                    $line->quantity, // Ordremængde
                    $line->unit_price, // Beløb pr. enhed.
                    null, // Priser fra SAP
                    $line->psp_element, // PSP-element nr.
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

    private function getInvoiceDescription(object $project)
    {
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
        return (null != $values) ? (object) array_merge($values, (array) $o) : $o;
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

    private function export($data)
    {
        $this->output->write($data);

        $message = (new \Swift_Message('Hello Email'))
            ->setFrom('send@example.com')
            ->setTo('recipient@example.com')
            ->setBody($data, 'text/plain')
            ->attach(new \Swift_Attachment($data, 'stuff.csv', 'text/csv'));

        $this->mailer->send($message);
    }
}

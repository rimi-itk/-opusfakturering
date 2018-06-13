<?php

/*
 * This file is part of Opus-fakturering.
 *
 * (c) 2017–2018 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Command;

use App\Service\HarvestApi;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HarvestExportCommand extends BaseCommand
{
    /** @var HarvestApi */
    private $harvestApi;

    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    public function __construct(HarvestApi $harvestApi)
    {
        parent::__construct();
        $this->harvestApi = $harvestApi;
    }

    public function configure()
    {
        $this->setName('app:harvest:export')
            ->setDescription('Harvest export');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $invoices = $this->getInvoices();
        $data = $this->process($invoices);
        $csv = $this->format($data);
        $output->write($csv);
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
        $rows = [];
        foreach ($invoices as $invoice) {
            if (0 === count($invoice->line_items)) {
                continue;
            }

            $project = $this->harvestApi->getProject($invoice->line_items[0]->project->id);
            if (null === $project) {
                continue;
            }

            $payerId = $this->getPayerId($project);

            if (null === $payerId) {
                echo 'No payer id!', PHP_EOL;
                continue;
            }

            $rows[] = [
                'H', // Linietype
                $payerId, // Ordregiver
                null, // Fakturamodtager
                $invoice->issue_date, // Fakturadato
                $invoice->issue_date, // Bilagsdato
                '0020', // Salgsorganisation
                '10', // Salgskanal
                '20', // Division
                'ZIRA', // Ordreart
                null, // Ekstern bilagsnr.
                null, // YdelsesModtagerNummerKode
                null, // Ydelsesmodtager
                null, // Kundens konto
                null, // Indkøbsordrenr.
                null, // Kunderef.ID
                null, // Toptekst
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
                $rows[] = [
                    'L', // Linietype
                    '0087', // Materiale (vare)nr.
                    null, // Beskrivelse
                    $item->quantity, // Ordremængde
                    $item->unit_price, // Beløb pr. enhed.
                    null, // Priser fra SAP
                    'PSP-ITK', // PSP-element nr.
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
}

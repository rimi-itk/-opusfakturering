<?php

/*
 * This file is part of Opus-fakturering.
 *
 * (c) 2017–2018 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Command;

use App\Entity\Account;
use App\Service\Exporter\AbstractExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HarvestExportCommand extends BaseCommand
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
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
        $verbose = $this->input->getOption('verbose');

        $accounts = $this->entityManager->getRepository(Account::class)->findAll();
        foreach ($accounts as $account) {
            if ($verbose) {
                $this->output->writeln(sprintf('Account: %s (%s)', $account->getName(), $account->getType()));
            }
            $exporter = AbstractExporter::getExporter($account, $this->entityManager);
            $exporter->run();
        }
    }
}

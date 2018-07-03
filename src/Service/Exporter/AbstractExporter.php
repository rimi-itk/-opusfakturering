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
use App\Service\Harvest\HarvestApi;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractExporter
{
    /** @var Account */
    protected $account;

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var \Swift_Mailer */
    protected $mailer;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(Account $account, EntityManagerInterface $entityManager)
    {
        $this->validateAccount($account);
        $this->account = $account;
        $this->entityManager = $entityManager;
    }

    public function setMailer(\Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;

        return $this;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    public function setHarvestApi(HarvestApi $harvestApi)
    {
        $this->harvestApi = $harvestApi;
    }

    abstract public function validateAccount(Account $account);

    abstract public function getInvoices();

    abstract public function process(array $invoices);

    abstract public function format(array $data);

    abstract public function export(array $csv);

    protected function info($message, array $context = [])
    {
        if (null !== $this->logger) {
            $this->logger->info($message, $context);
        }
    }

    protected function debug($message, array $context = [])
    {
        if (null !== $this->logger) {
            $this->logger->debug($message, $context);
        }
    }

    protected function error($message, array $context = [])
    {
        if (null !== $this->logger) {
            $this->logger->error($message, $context);
        }
    }

    public function run()
    {
        $invoices = $this->getInvoices();
        $data = $this->process($invoices);
        $csv = $this->format($data);
        $this->export($csv);
        $this->entityManager->flush();
    }
}

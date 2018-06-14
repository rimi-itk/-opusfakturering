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
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractExporter
{
    /** @var Account */
    protected $account;

    /** @var EntityManagerInterface */
    protected $entityManager;

    public function __construct(Account $account, EntityManagerInterface $entityManager)
    {
        $this->validateAccount($account);
        $this->account = $account;
        $this->entityManager = $entityManager;
    }

    public static function getExporter(Account $account, EntityManagerInterface $entityManager)
    {
        switch ($account->getType()) {
            case 'harvest':
                return new HarvestExporter($account, $entityManager);
        }

        throw new InvalidAccountException();
    }

    abstract public function validateAccount(Account $account);

    public function run()
    {
        $invoices = $this->getInvoices();
        $data = $this->process($invoices);
        $csv = $this->format($data);
        $this->export($csv);
        $this->entityManager->flush();
    }
}

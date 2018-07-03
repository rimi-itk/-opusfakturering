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
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ExporterFactory
{
    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var \Swift_Mailer */
    protected $mailer;

    /** @var HarvestApi */
    protected $harvestApi;

    public function __construct(EntityManagerInterface $entityManager, \Swift_Mailer $mailer, HarvestApi $harvestApi)
    {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->harvestApi = $harvestApi;
    }

    public function getExporter(Account $account, EntityManagerInterface $entityManager = null, LoggerInterface $logger = null)
    {
        $exporter = null;
        $type = $account->getType();
        switch ($type) {
            case HarvestExporter::ACCOUNT_TYPE:
                $exporter = new HarvestExporter($account, $entityManager ?? $this->entityManager, $this->harvestApi);
                break;
        }

        if (null === $exporter) {
            throw new InvalidAccountException($type);
        }

        $exporter
            ->setLogger($logger)
            ->setMailer($this->mailer);

        return $exporter;
    }
}

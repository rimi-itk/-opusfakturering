<?php

/*
 * This file is part of Opus-fakturering.
 *
 * (c) 2017–2018 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\ORM\EntityRepository;

class ProcessedInvoiceRepository extends EntityRepository
{
    public function create(string $type, string $identifier)
    {
        $entity = new Invoice($type, $identifier);
        $this->getEntityManager()->persist($entity);
    }
}

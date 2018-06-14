<?php
/**
 * Created by PhpStorm.
 * User: rimi
 * Date: 13/06/2018
 * Time: 22.18
 */

namespace App\Repository;


use App\Entity\ProcessedInvoice;
use Doctrine\ORM\EntityRepository;

class ProcessedInvoiceRepository extends EntityRepository
{
    public function create(string $type, string $identifier) {
        $entity = new ProcessedInvoice($type, $identifier);
        $this->getEntityManager()->persist($entity);
    }
}

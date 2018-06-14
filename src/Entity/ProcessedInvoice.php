<?php
/**
 * Created by PhpStorm.
 * User: rimi
 * Date: 13/06/2018
 * Time: 22.10
 */

namespace App\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProcessedInvoiceRepository")
 */
class ProcessedInvoice
{
    /**
     * @ORM\Column(type="string", length=255)
     * @ORM\Id
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     * @ORM\Id
     */
    private $identifier;

    public function __construct(string $type, string $identifier)
    {
        $this->type = $type;
        $this->identifier = $identifier;
    }
}

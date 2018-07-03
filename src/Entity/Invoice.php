<?php

/*
 * This file is part of Opus-fakturering.
 *
 * (c) 2017–2018 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProcessedInvoiceRepository")
 */
class Invoice
{
    /**
     * @var Account
     * @ORM\ManyToOne(targetEntity="App\Entity\Account")
     * @ORM\Id
     */
    private $account;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     * @ORM\Id
     */
    private $identifier;

    /**
     * @var array
     * @ORM\Column(type="json_array")
     */
    private $data;

    public function __construct(Account $account, string $identifier, array $data = null)
    {
        $this->account = $account;
        $this->identifier = $identifier;
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param mixed $account
     */
    public function setAccount(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param mixed $identifier
     */
    public function setIdentifier(string $identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}

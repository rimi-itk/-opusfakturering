<?php

/*
 * This file is part of Opus-fakturering.
 *
 * (c) 2017–2018 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Service\Harvest;

use GuzzleHttp\Client;

/**
 * Class HarvestApi.
 *
 * @see https://help.getharvest.com/api-v2/
 * @see https://id.getharvest.com/developers
 */
class HarvestApi
{
    const INVOICE_STATE_DRAFT = 'draft';

    /** @var array */
    private $config;

    /** @var Client */
    private $client;

    /** @var \Exception */
    private $exception;

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * @see https://help.getharvest.com/api-v2/invoices-api/invoices/invoices/#list-all-invoices
     */
    public function getInvoices(array $query = null)
    {
        return $this->get('invoices/', $query);
    }

    public function getInvoice($id)
    {
        return $this->get('invoices/'.$id);
    }

    /**
     * https://help.getharvest.com/api-v2/projects-api/projects/projects/.
     *
     * @param $id
     *
     * @return mixed|null
     */
    public function getProject($id)
    {
        return $this->get('projects/'.$id);
    }

    private function get($url, array $query = null)
    {
        $this->exception = null;
        try {
            $response = $this->getClient()->request('GET', $url, ['query' => $query]);

            return json_decode((string) $response->getBody());
        } catch (\Exception $exception) {
            $this->exception = $exception;

            return null;
        }
    }

    private function getClient()
    {
        if (null === $this->client) {
            $this->client = new Client([
                'base_uri' => 'https://api.harvestapp.com/api/v2/',
                'headers' => [
                    'Harvest-Account-ID' => $this->config['account'],
                    'Authorization' => 'Bearer '.$this->config['token'],
                    'User-Agent' => 'Harvest API Example',
                ],
                'debug' => isset($this->config['debug']) && $this->config['debug'],
            ]);
        }

        return $this->client;
    }
}

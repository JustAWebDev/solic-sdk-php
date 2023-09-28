<?php

namespace Solic;

use GuzzleHttp\Client;
use Psr\Http\Message\StreamInterface;

class SolicService
{
    protected string $token;
    protected string $serviceUrl = 'https://solic.io/api/';
    protected Client $client;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->client = new Client([
            'base_uri' => $this->serviceUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);
    }

    public function getServiceUrl(): string
    {
        return $this->serviceUrl;
    }

    public function listForms(): StreamInterface
    {
        $response = $this->client->get('forms');

        return $response->getBody();
    }

    public function getForm(string $id): StreamInterface
    {
        $response = $this->client->get('forms/show/' . $id);

        return $response->getBody();
    }

    public function checkForm(array $data, ?string $ipAddress = null): StreamInterface
    {
        if (!empty($ipAddress)) {
            $data['ip_address'] = $ipAddress;
        } else {
            $data['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }
        $response = $this->client->post('forms/store', ['json' => $data]);

        return $response->getBody();
    }
}
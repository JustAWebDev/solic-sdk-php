<?php

namespace Solic;

use GuzzleHttp\Client;
use Psr\Http\Message\StreamInterface;

class SolicService
{
    protected string $token;
    protected string $serviceUrl;
    protected Client $client;

    public function __construct(string $token, string $serviceUrl = 'https://solic.io/api/')
    {
        $this->token = $token;
        $this->serviceUrl = $serviceUrl;
        $this->client = new Client([
            'base_uri' => $this->serviceUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
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

    public function checkForm(array $data, ?string $ipAddress = null, ?string $userAgent = null): StreamInterface
    {
        if (!empty($ipAddress)) {
            $data['meta']['ip_address'] = $ipAddress;
        } else {
            $data['meta']['ip_address'] = $_SERVER['REMOTE_ADDR'];
        }

        if (!empty($userAgent)) {
            $data['meta']['user_agent'] = $userAgent;
        } else {
            $data['meta']['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        $response = $this->client->post('forms/store', ['json' => $data]);

        return $response->getBody();
    }
}
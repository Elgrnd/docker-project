<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProxmoxService
{
    private HttpClientInterface $client;
    private string $apiUrl;
    private string $tokenId;
    private string $secret;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->apiUrl = $_ENV['PROXMOX_API_URL'];
        $this->tokenId = $_ENV['PROXMOX_TOKEN_ID'];
        $this->secret = $_ENV['PROXMOX_SECRET'];
    }
}
<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
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

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function cloneUserVM(string $username): ?int
    {
        $vmid = rand(200, 999);
        $data = [
            'newid' => $vmid,
            'name' => "vm_$username",
            'full' => 1,
            'target' => 'proxmox',
        ];

        $response = $this->client->request(
            'POST',
            "{$this->apiUrl}/nodes/proxmox/qemu/100/clone",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'body' => $data,
            ]
        );

        return $vmid;
    }
}
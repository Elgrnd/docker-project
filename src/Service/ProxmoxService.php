<?php

namespace App\Service;

use phpDocumentor\Reflection\Types\Integer;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
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
        $this->secret = $_ENV['PROXMOX_TOKEN_SECRET'];
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function cloneUserVM(string $username): ?int
    {
        $vmid = rand(200, 999);
        $data = [
            'newid' => $vmid,
            'name' => "vm-$username",
            'full' => 1,
            'target' => 'proxmox',
            'storage' => 'local-lvm',
        ];

        $response = $this->client->request(
            'POST',
            "{$this->apiUrl}/nodes/proxmox/qemu/100/clone",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'body' => $data,
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );

        return $vmid;
    }
}
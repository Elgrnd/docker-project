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
        $this->secret = $_ENV['PROXMOX_TOKEN_SECRET'];
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
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
            "{$this->apiUrl}/nodes/proxmox/qemu/101/clone",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'body' => $data,
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );

        $data = $response->toArray();
        $upid = $data["data"];


        do {
            sleep(2);
            $status = $this->getTaskStatus($upid);
        } while ($status['status'] !== 'stopped' || $status['exitstatus'] !== 'OK');

        $this->startVM($vmid);

        return $vmid;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function deleteVM(string $vmid): bool {
        $this->stopVM($vmid);

        $response = $this->client->request(
            'DELETE',
            "{$this->apiUrl}/nodes/proxmox/qemu/$vmid",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );
        return in_array($response->getStatusCode(), [200, 204]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getVMIp(string $vmid): ?string {
        $response = $this->client->request(
            'GET',
            "{$this->apiUrl}/nodes/proxmox/qemu/{$vmid}/agent/network-get-interfaces",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );

        $data = json_decode($response->getContent(), true);

        foreach ($data['data']['result'] as $interface) {
            foreach ($interface['ip-addresses'] ?? [] as $ipInfo) {
                if (($ipInfo['ip-address-type'] ?? '') === 'ipv4' && ($ipInfo['ip-address'] ?? '') !== '127.0.0.1') {
                    return $ipInfo['ip-address'];
                }
            }
        }

        return null;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function startVM(string $vmid): bool
    {
        $response = $this->client->request(
            'POST',
            "{$this->apiUrl}/nodes/proxmox/qemu/{$vmid}/status/start",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );


        return in_array($response->getStatusCode(), [200, 202]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function stopVM(string $vmid): bool
    {
        $response = $this->client->request(
            'POST',
            "{$this->apiUrl}/nodes/proxmox/qemu/{$vmid}/status/stop",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );

        $upid = $response->toArray()['data'] ?? null;
        if (!$upid) {
            return false;
        }

        do {
            sleep(2);
            $status = $this->getTaskStatus($upid);
        } while (($status['status'] ?? '') !== 'stopped' || ($status['exitstatus'] ?? '') !== 'OK');


        return in_array($response->getStatusCode(), [200, 202]);
    }

    private function getTaskStatus($upid)
    {
        $response = $this->client->request(
            'GET',
            "{$this->apiUrl}/nodes/proxmox/tasks/{$upid}/status",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );

        $data = $response->toArray();
        return $data['data'] ?? [];
    }
}
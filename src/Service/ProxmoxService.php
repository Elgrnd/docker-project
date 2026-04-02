<?php

namespace App\Service;

use App\Entity\VirtualMachine;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpKernel\KernelInterface;
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
    private EntityManagerInterface $entityManager;
    private string $projectDir;

    public function __construct(
        HttpClientInterface $client,
        KernelInterface $kernel,
        EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->projectDir = $kernel->getProjectDir();
        $this->entityManager = $entityManager;
        $this->apiUrl = $_ENV['PROXMOX_API_URL'];
        $this->tokenId = $_ENV['PROXMOX_TOKEN_ID'];
        $this->secret = $_ENV['PROXMOX_TOKEN_SECRET'];
    }

    public function cloneUserVmAsynchrone(string $login): void
    {
        $command = sprintf(
            'php %s/bin/console app:create-vm %s > %s/var/log/create-vm.log 2>&1 &',
            $this->projectDir,
            $login,
            $this->projectDir
        );

        exec($command);
    }

    public function cloneGroupVmAsynchrone(int $id): void
    {
        $command = sprintf(
            'php %s/bin/console app:create-vm-group %d > /dev/null 2>&1 &',
            $this->projectDir,
            $id
        );

        exec($command);
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     */
    public function cloneVm(string $name, int $machineId): ?int
    {
        $vmid = rand(200, 999);
        $data = [
            'newid' => $vmid,
            'name' => "vm-$name",
            'full' => 1,
            'target' => 'pve',
            'storage' => 'local-lvm',
        ];

        $response = $this->client->request(
            'POST',
            "{$this->apiUrl}/nodes/pve/qemu/100/clone",
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

        $newIp = "192.168.1." . ($machineId + 100);

        $this->client->request(
            'POST',
            "{$this->apiUrl}/nodes/pve/qemu/$vmid/config",
            [
                'headers' => ['Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}"],
                'json' => [
                    'ipconfig0' => "ip=$newIp/24,gw=192.168.1.1",
                    'nameserver' => '10.10.1.1',
                ],
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );

        $this->startVM($vmid);

        return $vmid;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function deleteVM(string $vmid): bool
    {
        $this->stopVM($vmid);

        $response = $this->client->request(
            'DELETE',
            "{$this->apiUrl}/nodes/pve/qemu/$vmid",
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
    public function verifVMIp(VirtualMachine $virtualMachine): ?string
    {
        if(!$virtualMachine->getVmIp()) {
            $response = $this->client->request(
                'GET',
                "{$this->apiUrl}/nodes/pve/qemu/{$virtualMachine->getVmId()}/agent/network-get-interfaces",
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
                        $virtualMachine->setVmIp($ipInfo['ip-address']);
                        $this->entityManager->flush();
                        return $ipInfo['ip-address'];
                    }
                }
            }

            return null;
        } else {
            return $virtualMachine->getVmIp();
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function startVM(string $vmid): bool
    {
        $response = $this->client->request(
            'POST',
            "{$this->apiUrl}/nodes/pve/qemu/{$vmid}/status/start",
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
     * @param string $vmid
     * @return bool
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function stopVM(string $vmid): bool
    {
        $response = $this->client->request(
            'POST',
            "{$this->apiUrl}/nodes/pve/qemu/{$vmid}/status/stop",
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



    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function getTaskStatus($upid)
    {
        $response = $this->client->request(
            'GET',
            "{$this->apiUrl}/nodes/pve/tasks/{$upid}/status",
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


    /**
     * Liste toutes les VM du node Proxmox.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws DecodingExceptionInterface
     */
    public function listVMs(): array
    {
        $response = $this->client->request(
            'GET',
            "{$this->apiUrl}/nodes/pve/qemu",
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

    /**
     * Récupère l’état courant d’une VM (CPU, RAM, disque, uptime, statut…).
     *
     * @return array<string, mixed>
     *
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws DecodingExceptionInterface
     */
    public function getVMRuntimeStatus(int $vmid): array
    {
        $response = $this->client->request(
            'GET',
            "{$this->apiUrl}/nodes/pve/qemu/{$vmid}/status/current",
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

    /**
     * Vue d’ensemble pour le panneau admin :
     * - vmid, name
     * - status (running/stopped/...)
     * - cpu, mem/maxmem, disk/maxdisk, uptime
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminVmOverview(): array
    {
        try {
            $vms = $this->listVMs();
        } catch (\Throwable $e) {
            return [];
        }

        $result = [];

        foreach ($vms as $vm) {
            $vmid = (int)($vm['vmid'] ?? 0);
            if ($vmid <= 0) {
                continue;
            }

            $name = $vm['name'] ?? ('vm-' . $vmid);

            try {
                $runtime = $this->getVMRuntimeStatus($vmid);
            } catch (\Throwable $e) {
                $result[] = [
                    'vmid' => $vmid,
                    'name' => $name,
                    'status' => 'unknown',
                    'cpu' => null,
                    'maxcpu' => null,
                    'mem' => null,
                    'maxmem' => null,
                    'disk' => null,
                    'maxdisk' => null,
                    'uptime' => null,
                    'error' => $e->getMessage(),
                ];
                continue;
            }

            $result[] = [
                'vmid' => $vmid,
                'name' => $name,
                'status' => $runtime['status'] ?? 'unknown',
                'cpu' => $runtime['cpu'] ?? null,
                'maxcpu' => $runtime['maxcpu'] ?? null,
                'mem' => $runtime['mem'] ?? null,
                'maxmem' => $runtime['maxmem'] ?? null,
                'disk' => $runtime['disk'] ?? null,
                'maxdisk' => $runtime['maxdisk'] ?? null,
                'uptime' => $runtime['uptime'] ?? null,
            ];
        }

        return $result;
    }

    public function listClusterVmResources(): array
    {
        $response = $this->client->request(
            'GET',
            "{$this->apiUrl}/cluster/resources",
            [
                'headers' => [
                    'Authorization' => "PVEAPIToken={$this->tokenId}={$this->secret}",
                ],
                'query' => [
                    'type' => 'vm',
                ],
                'verify_peer' => false,
                'verify_host' => false,
            ]
        );

        $data = $response->toArray();

        return $data['data'] ?? [];
    }

    /**
     * Monitoring admin global: toutes les VM visibles par le token (tous nodes, qemu + lxc)
     * Retour formaté comme ton Twig attend.
     */
    public function getAdminVmOverviewAllCluster(): array
    {
        $resources = $this->listClusterVmResources();
        $result = [];

        foreach ($resources as $r) {
            // id ressemble à "qemu/100" ou "lxc/101"
            $id = $r['id'] ?? '';
            $node = $r['node'] ?? 'proxmox';

            if ($id === '' || strpos($id, '/') === false) {
                continue;
            }

            [$type, $vmidStr] = explode('/', $id, 2);
            $vmid = (int) $vmidStr;
            if ($vmid <= 0) {
                continue;
            }

            // métriques déjà présentes dans /cluster/resources:
            // cpu, mem, maxmem, disk, maxdisk, uptime, status, name...
            $result[] = [
                'vmid'    => $vmid,
                'name'    => $r['name'] ?? $id,
                'status'  => $r['status'] ?? 'unknown',
                'cpu'     => $r['cpu'] ?? null,
                'maxcpu'  => $r['maxcpu'] ?? null,
                'mem'     => $r['mem'] ?? null,
                'maxmem'  => $r['maxmem'] ?? null,
                'disk'    => $r['disk'] ?? null,
                'maxdisk' => $r['maxdisk'] ?? null,
                'uptime'  => $r['uptime'] ?? null,

                // bonus utile au debug (facultatif)
                'node'    => $node,
                'type'    => $type,
            ];
        }

        return $result;
    }


}
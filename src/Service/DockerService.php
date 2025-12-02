<?php

namespace App\Service;

class DockerService
{

    private string $dockerPath = '/usr/bin/docker';
    private string $sshUser = 'root';
    private string $sshPrivateKey = '/var/www/.ssh/projet_vm_id_rsa';
    private string $hoteProxMoxIp;

    public function __construct()
    {
        $this->hoteProxMoxIp = $_ENV['PROXMOX_HOTE'];
    }

    public function runInVm(string $cmd, string $vmIp): string {
        $proxyCmd = sprintf(
            'ssh -i %s -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -W %%h:%%p %s@%s',
            escapeshellarg($this->sshPrivateKey),
            $this->sshUser,
            $this->hoteProxMoxIp
        );

        $sshCommand = 'ssh -i ' . escapeshellarg($this->sshPrivateKey)
            . ' -o UserKnownHostsFile=/dev/null'
            . ' -o StrictHostKeyChecking=no'
            . ' -o ProxyCommand="' . $proxyCmd . '"'
            . ' ' . escapeshellarg($this->sshUser . '@' . $vmIp)
            . ' ' . escapeshellarg($cmd);

        return trim(shell_exec($sshCommand));
    }



    public function listContainers(string $vmIp): array
    {
        $cmd = $this->dockerPath . ' ps -a --format "{{.ID}}|{{.Names}}|{{.Status}}"';
        $output = $this->runInVm($cmd, $vmIp);

        $containers = [];

        $lines = array_filter(array_map('trim', explode("\n", $output)));


        foreach ($lines as $line) {
            $parts = explode('|', $line);
            [$id, $name, $status] = $parts;
            $containers[] = [
                'id' => $id,
                'name' => $name,
                'status' => $status,
            ];
        }
        return $containers;
    }

    public function startContainer(string $id, $vmIp): array
    {
        return $this->executeCommand("start", $id, $vmIp);
    }

    public function stopContainer(string $id, $vmIp): array
    {
        return $this->executeCommand("stop", $id, $vmIp);
    }

    public function removeContainer(string $id, $vmIp): array
    {
        return $this->executeCommand("rm", $id, $vmIp);
    }

    private function executeCommand(string $action, string $id, $vmIp): array
    {
        $command = "$this->dockerPath $action $id 2>&1";
        $output = $this->runInVm($command, $vmIp);
        return [
            'success' => str_contains($output, $id) || str_contains($output, 'running'),
            'message' => trim($output),
        ];
    }


}
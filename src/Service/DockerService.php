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

    private function buildProxyCommand(): string
    {
        return sprintf(
            'ssh -i %s -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -W %%h:%%p %s@%s',
            escapeshellarg($this->sshPrivateKey),
            $this->sshUser,
            $this->hoteProxMoxIp
        );
    }

    private function getSshBaseOptions(): string
    {
        return sprintf(
            '-i %s -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o ProxyCommand="%s"',
            escapeshellarg($this->sshPrivateKey),
            $this->buildProxyCommand()
        );
    }


    public function runInVm(string $cmd, string $vmIp): string
    {
        $sshCommand = sprintf(
            'ssh %s %s %s',
            $this->getSshBaseOptions(),
            escapeshellarg($this->sshUser . '@' . $vmIp),
            escapeshellarg($cmd)
        );

        return trim(shell_exec($sshCommand));
    }

    public function sendFileToVm(string $localFilePath, string $remotePath, string $vmIp): string
    {
        $scpCommand = sprintf(
            'scp %s %s %s',
            $this->getSshBaseOptions(),
            escapeshellarg($localFilePath),
            escapeshellarg($this->sshUser . '@' . $vmIp . ':' . $remotePath)
        );

        return trim(shell_exec($scpCommand . ' 2>&1') ?? '');
    }

    public function sendContentToVm(string $content, string $remotePath, string $vmIp): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'vm_');
        file_put_contents($tmpFile, $content);

        $scpCommand = sprintf(
            'scp %s %s %s',
            $this->getSshBaseOptions(),
            escapeshellarg($tmpFile),
            escapeshellarg($this->sshUser . '@' . $vmIp . ':' . $remotePath)
        );

        $output = shell_exec($scpCommand . ' 2>&1');
        unlink($tmpFile);

        return trim($output ?? '');
    }

    public function deployZipInVm(
        string $localZipPath,
        string $remoteDir,
        string $vmIp
    ): void
    {
        $remoteZip = $remoteDir . '.zip';

        $this->sendFileToVm($localZipPath, $remoteZip, $vmIp);

        $this->runInVm(sprintf('mkdir -p %s', escapeshellarg($remoteDir)), $vmIp);
        $this->runInVm(sprintf('unzip -o %s -d %s', escapeshellarg($remoteZip), escapeshellarg($remoteDir)), $vmIp);
        $this->runInVm(sprintf('rm -f %s', escapeshellarg($remoteZip)), $vmIp);

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

    public function listServices(string $vmIp): array
    {
        $cmd = $this->dockerPath . ' compose ps --format "{{.Name}}|{{.Service}}|{{.Image}}|{{.Status}}|{{.Ports}}"';
        $output = $this->runInVm($cmd, $vmIp);

        $services = [];

        $lines = array_filter(array_map('trim', explode("\n", $output)));

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            [$name, $service, $image, $status, $ports] = $parts;
            $services[] = [
                'name' => $name,
                'service' => $service,
                'image' => $image,
                'status' => $status,
                'ports' => $ports,
            ];
        }
        return $services;
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
<?php

namespace App\Service;

use Random\RandomException;

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
        $cmd = $this->dockerPath . ' ps -a --format "{{.ID}}|{{.Names}}|{{.Status}}|{{.Ports}}"';
        $output = $this->runInVm($cmd, $vmIp);

        $containers = [];

        $lines = array_filter(array_map('trim', explode("\n", $output)));


        foreach ($lines as $line) {
            $parts = explode('|', $line);
            [$id, $name, $status, $ports] = $parts;
            $containers[] = [
                'id' => $id,
                'name' => $name,
                'status' => $status,
                'ports'  => $ports,
                'url' => $this->getWebUrlFromPorts($vmIp, $ports),
            ];
        }
        return $containers;
    }

    public function listServices(string $vmIp): array
    {
        $cmd = '/usr/bin/docker inspect $(/usr/bin/docker ps -q) --format \'{{json .}}\'';

        $output = $this->runInVm($cmd, $vmIp);

        $services = [];

        $lines = array_filter(array_map('trim', explode("\n", $output)));

        foreach ($lines as $line) {
            $data = json_decode($line, true);

            if (!$data) {
                continue;
            }

            $services[] = [
                'name' => ltrim($data['Name'] ?? '', '/'),
                'service' => $data['Config']['Labels']['com.docker.compose.service'] ?? '',
                'image' => $data['Config']['Image'] ?? '',
                'status' => $data['State']['Status'] ?? '',
                'ports' => $data['NetworkSettings']['Ports'] ?? [],
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

    private function getWebUrlFromPorts(string $vmIp, string $ports) : ?string
    {
        $url = null;

        if (str_contains($ports, '->80/tcp') && preg_match('/:(\d+)->80\/tcp/', $ports, $m)) {
            $url = 'http://' . $vmIp . ':' . $m[1];
        }

        if (str_contains($ports, '->443/tcp') && preg_match('/:(\d+)->443\/tcp/', $ports, $m)) {
            $url = 'https://' . $vmIp . ':' . $m[1];
        }

        return $url;
    }

    public function fetchFileFromVm(string $remotePath, string $localFilePath, string $vmIp): string
    {
        $dir = dirname($localFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $scpCommand = sprintf(
            'scp %s -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no %s %s',
            $this->getSshBaseOptions(),
            escapeshellarg($this->sshUser . '@' . $vmIp . ':' . $remotePath),
            escapeshellarg($localFilePath)
        );


        return trim(shell_exec($scpCommand . ' 2>&1') ?? '');
    }

    /**
     * @throws RandomException
     */
    public function pullDirFromVmAsTarAndExtract(string $remoteDir, string $localExtractDir, string $vmIp): string
    {
        $remoteTar = '/tmp/copievm_' . bin2hex(random_bytes(6)) . '.tar.gz';

        $tarCmd = sprintf(
            'cd %s && tar --exclude="./.*" -czf %s . 2>&1',
            escapeshellarg('/root'),
            escapeshellarg($remoteTar)
        );

        $tarOut = $this->runInVm($tarCmd, $vmIp);

        $check = $this->runInVm('test -f ' . escapeshellarg($remoteTar) . ' && echo OK || echo NO', $vmIp);
        if (trim($check) !== 'OK') {
            throw new \RuntimeException("Création tar échouée: " . $tarOut);
        }

        $localTar = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($remoteTar);
        $err = $this->fetchFileFromVm($remoteTar, $localTar, $vmIp);
        if (!is_file($localTar) || filesize($localTar) === 0) {
            throw new \RuntimeException("SCP échoué: " . $err);
        }

        if (!is_dir($localExtractDir)) {
            mkdir($localExtractDir, 0775, true);
        }

        $extractCmd = sprintf(
            'tar -xzf %s -C %s 2>&1',
            escapeshellarg($localTar),
            escapeshellarg($localExtractDir)
        );

        $extractOut = shell_exec($extractCmd);
        @unlink($localTar);

        return trim($extractOut ?? '');
    }


}
<?php

namespace App\Service;

class SftpCredentialService
{
    private string $keysDir;

    public function __construct(string $projectDir)
    {
        $this->keysDir = $projectDir . '/var/sftp_keys';
    }

    public function generateKeyPair(string $login): array
    {
        $userDir = $this->keysDir . '/' . $login;

        if (!is_dir($userDir)) {
            mkdir($userDir, 0700, true);
        }

        $privateKeyPath = $userDir . '/id_rsa';
        $publicKeyPath  = $userDir . '/id_rsa.pub';

        if (!file_exists($privateKeyPath)) {
            $cmd = sprintf(
                'ssh-keygen -t rsa -b 4096 -f %s -N "" -C %s 2>&1',
                escapeshellarg($privateKeyPath),
                escapeshellarg('sftp-' . $login)
            );
            shell_exec($cmd);
        }

        return [
            'privateKeyPath' => $privateKeyPath,
            'privateKey'     => file_get_contents($privateKeyPath),
            'publicKey'      => file_get_contents($publicKeyPath),
        ];
    }

}
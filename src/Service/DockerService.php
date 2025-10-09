<?php

namespace App\Service;

class DockerService
{
    public function listContainers(bool $all = false): array
    {

        $dockerPath = '/usr/bin/docker';
        $cmd = $all ? "$dockerPath ps -a --format \"{{.ID}}|{{.Names}}|{{.Status}}\" 2>&1" :
            "$dockerPath ps -a --format \"{{.ID}}|{{.Names}}|{{.Status}}\" 2>&1";
        $output = shell_exec($cmd);


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

    public function startContainer(string $id): void
    {
        shell_exec("/usr/bin/docker start " . escapeshellarg($id));
    }

    public function stopContainer(string $id): void
    {
        shell_exec("/usr/bin/docker stop " . escapeshellarg($id));
    }

    public function removeContainer(string $id): void
    {
        shell_exec("/usr/bin/docker rm " . escapeshellarg($id));
    }


}
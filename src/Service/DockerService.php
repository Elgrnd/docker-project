<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Container;

class DockerService
{
    public function listContainers(bool $all = false): array
    {
        $cmd = $all ? 'docker ps -a -- format "{{.ID}}|{{.Names}}|{{.Status}}"' : 'docker ps --format "{{.ID}}|{{.Names}}|{{.Status}}"';

        $output = shell_exec($cmd);

        $containers = [];
        foreach (explode("\n", $output) as $line) {
            [$id, $name, $status] = explode('|', $line);
            $containers[] = [
                'id' => $id,
                'name' => $name,
                'status' => $status,
            ];
        }
        return $containers;
    }

}
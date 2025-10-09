<?php

namespace App\Service;

class DockerService
{

    private string $dockerPath = '/usr/bin/docker';

    public function listContainers(): array
    {

        $cmd = "$this->dockerPath ps -a --format \"{{.ID}}|{{.Names}}|{{.Status}}\" 2>&1";
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

    public function startContainer(string $id): array
    {
        return $this->executeCommand("start", $id);
    }

    public function stopContainer(string $id): array
    {
        return $this->executeCommand("stop", $id);
    }

    public function removeContainer(string $id): array
    {
        return $this->executeCommand("rm", $id);
    }

    private function executeCommand(string $action, string $id) : array {
        $command = escapeshellcmd("$this->dockerPath $action" . escapeshellarg($id) . " 2>&1");
        exec($command, $output, $returnCode);
        if ($returnCode === 0) {
            return [
                'success' => true,
                'message' => "Container $action successful: " . implode(' ', $output)
            ];
        } else {
            return [
                'success' => false,
                'message' => "Error while running '$action' on container $id: " . implode(' ', $output)
            ];
        }
    }


}
<?php

namespace App\Service;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class GitlabApiService
{
    private array $allowedHosts = [
        'gitlab.com',
        'gitlabinfo.iutmontp.univ-montp2.fr',
        // ajouter d'autres domaines GitLab autorisés ici
    ];

    public function isValidGitlabUrl(?string $url): bool
    {
        if (!$url) return false;

        $parsed = $this->parseGitlabUrl($url);

        if ($parsed === null || empty($parsed['host']) || empty($parsed['project'])) {
            return false;
        }

        if (!in_array($parsed['host'], $this->allowedHosts, true)) {
            return false;
        }

        return true;
    }

    /**
     * Détecte un projet privé via l'URL web du projet (redirection vers /users/sign_in).
     */
    public function isPrivateProjectUrl(string $url): bool
    {
        $parsed = $this->parseGitlabUrl($url);
        if (!$parsed) return false;

        $projectWebUrl = "https://{$parsed['host']}/{$parsed['namespace']}/{$parsed['project']}";

        $ch = curl_init($projectWebUrl);

        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false, // on veut voir la redirection
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HEADER => true,
        ]);

        $headers = curl_exec($ch);

        if ($headers === false) {
            curl_close($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (in_array($httpCode, [301, 302, 303, 307, 308], true)) {
            if (preg_match('#^Location:\s*(.+)$#mi', $headers, $m)) {
                $location = trim($m[1]);
                if (str_contains($location, '/users/sign_in')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Vérifie que le token (PRIVATE-TOKEN) est valide via /api/v4/user.
     * @throws GitlabApiException
     */
    public function assertTokenValid(string $host, string $privateToken): void
    {
        $url = "https://$host/api/v4/user";
        $this->request($url, $privateToken);
    }

    private function projectExists(string $host, string $projectId): bool
    {
        // Détermination de la base API GitLab selon l'hôte
        $apiBase = match ($host) {
            'gitlab.com' => 'https://gitlab.com/api/v4/projects/',
            'gitlabinfo.iutmontp.univ-montp2.fr' => 'https://gitlabinfo.iutmontp.univ-montp2.fr/api/v4/projects/',
            default => null
        };

        if (!$apiBase) {
            return false;
        }

        $url = $apiBase . $projectId;

        try {
            $response = $this->request($url);

            // Si request() ne throw pas → HTTP < 400 → OK
            return true;

        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Vérifie que le projet est atteignable via /api/v4/projects/:id
     * @throws GitlabApiException|RuntimeException
     */
    public function assertProjectReachable(string $url, ?string $privateToken): void
    {
        $parsed = $this->parseGitlabUrl($url);
        if (!$parsed) {
            throw new RuntimeException("URL GitLab invalide.");
        }

        $apiUrl = "https://{$parsed['host']}/api/v4/projects/{$parsed['projectId']}";
        $this->request($apiUrl, $privateToken);
    }

    public function parseGitlabUrl(string $url): ?array
    {
        $parsed = parse_url($url);

        if (!is_array($parsed) || !isset($parsed['host']) || !isset($parsed['path'])) {
            return null;
        }

        $host = $parsed['host'];
        $path = trim($parsed['path'], '/');
        $parts = explode('/', $path);

        if (count($parts) < 2) {
            return null; // namespace/project manquant
        }

        // Recherche du segment "-"
        $dashIndex = array_search('-', $parts);

        // Détermination namespace + project
        if ($dashIndex === false) {
            // Pas de /-/
            $project = array_pop($parts);
            $namespaceParts = $parts;
            $branch = 'HEAD';
            $type = null;
            $file = null;
        } else {
            if ($dashIndex < 2) {
                return null; // structure incorrecte
            }
            $project = $parts[$dashIndex - 1];
            $namespaceParts = array_slice($parts, 0, $dashIndex - 1);
            $type = $parts[$dashIndex + 1] ?? null; // tree, blob, raw
            $branch = $parts[$dashIndex + 2] ?? 'HEAD';
            $file = isset($parts[$dashIndex + 3]) ? implode('/', array_slice($parts, $dashIndex + 3)) : null;
        }

        $namespace = implode('/', $namespaceParts);
        $projectId = rawurlencode("$namespace/$project");

        return [
            "host" => $host,
            "namespace" => $namespace,
            "project" => $project,
            "projectId" => $projectId,
            "branch" => $branch,
            "type" => $type,
            "file" => $file
        ];
    }

    /**
     * Appel API GitLab avec header PRIVATE-TOKEN optionnel.
     * @throws GitlabApiException
     */
    public function request(string $url, ?string $privateToken = null)
    {
        $ch = curl_init($url);

        $headers = [];
        if ($privateToken) {
            $headers[] = 'PRIVATE-TOKEN: ' . $privateToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException("Impossible de contacter GitLab : $curlError", 0);
        }

        if ($httpCode >= 400) {
            throw new GitlabApiException(
                $httpCode,
                "GitLab a refusé la requête (HTTP $httpCode).",
                $url
            );
        }

        if ($result === false || $result === null || $result === '') {
            return null;
        }

        $decoded = json_decode($result, true);
        return $decoded === null ? $result : $decoded;
    }


    public function buildTree(array $items): array
    {
        $tree = [];

        foreach ($items as $item) {
            if (!isset($item['path']) || !isset($item['type'])) {
                continue;
            }

            $parts = explode('/', $item['path']);
            $current = &$tree;

            foreach ($parts as $index => $part) {
                $isLast = ($index === count($parts) - 1);

                if ($isLast) {
                    // Si déjà existant et contient des enfants, on doit MERGER intelligemment
                    if (isset($current[$part]) && isset($current[$part]['children'])) {
                        $current[$part]['type'] = $item['type'];
                        $current[$part]['path'] = $item['path'];
                    } else {
                        $current[$part] = [
                            'type' => $item['type'],
                            'path' => $item['path']
                        ];
                    }
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = [
                            'type' => 'tree',
                            'children' => []
                        ];
                    } elseif (!isset($current[$part]['children'])) {
                        $existing = $current[$part];
                        $current[$part] = [
                            'type' => 'tree',
                            'path' => $existing['path'] ?? null,
                            'children' => []
                        ];
                    }

                    $current = &$current[$part]['children'];
                }
            }

            unset($current);
        }

        $this->sortTree($tree);

        return $tree;
    }

    private function sortTree(array &$nodes): void
    {
        foreach ($nodes as $key => &$node) {
            if (isset($node['children']) && is_array($node['children'])) {
                $this->sortTree($node['children']);
            }
        }
        unset($node);

        uasort($nodes, function ($a, $b) {
            $isTreeA = ($a['type'] ?? '') === 'tree';
            $isTreeB = ($b['type'] ?? '') === 'tree';

            if ($isTreeA !== $isTreeB) {
                return $isTreeA ? -1 : 1;
            }

            return 0;
        });
    }

    /**
     * Filtre une liste d'items GitLab "tree API" pour ne garder que les YAML
     * non vides et parseables.
     *
     * @return array filtered items
     */
    public function filterValidYamlFiles(array $items, string $host, string $projectId, string $branch, ?string $token): array
    {
        return array_values(array_filter($items, function ($item) use ($host, $projectId, $branch, $token) {
            if (!isset($item['path'])) return false;
            if (($item['type'] ?? null) !== 'blob') return false;

            $filename = basename($item['path']);
            if (!preg_match('/^[^.].*\.ya?ml$/i', $filename)) return false;

            $rawUrl = "https://$host/api/v4/projects/$projectId/repository/files/" . rawurlencode($item['path']) . "/raw?ref=$branch";

            try {
                $content = $this->request($rawUrl, $token);
            } catch (\Throwable $e) {
                return false;
            }

            if (!is_string($content) || trim($content) === '') return false;

            try {
                Yaml::parse($content);
            } catch (ParseException $e) {
                return false;
            }

            return true;
        }));
    }
}

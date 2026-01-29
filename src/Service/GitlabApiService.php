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

    public function getLatestCommitSha(string $host, string $projectId, string $branch, ?string $token): ?string
    {
        $url = "https://$host/api/v4/projects/$projectId/repository/commits?ref_name=" . rawurlencode($branch) . "&per_page=1";
        $res = $this->request($url, $token);

        if (!is_array($res) || count($res) === 0) return null;
        return is_array($res[0]) ? ($res[0]['id'] ?? null) : null;
    }

    /** @return array<int, array<string,mixed>> */
    public function listRepositoryTree(string $host, string $projectId, string $branch, ?string $token): array
    {
        $items = [];
        $page = 1;

        do {
            $apiUrl = "https://$host/api/v4/projects/$projectId/repository/tree?recursive=true&ref="
                . rawurlencode($branch)
                . "&per_page=100&page=$page";

            $result = $this->request($apiUrl, $token);

            if (!is_array($result) || count($result) === 0) break;

            $items = array_merge($items, $result);
            $page++;
        } while (true);

        return $items;
    }
}

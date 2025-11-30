<?php

namespace App\Service;

use RuntimeException;

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

        // Vérification que l'hôte est bien autorisé
        if (!in_array($parsed['host'], $this->allowedHosts, true)) {
            return false;
        }

        return true;
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
     * Vérifie qu'une URL GitLab pointe vers un projet réel et accessible.
     */
    public function isReachableGitlabProjectUrl(string $url): bool
    {
        $parsed = $this->parseGitlabUrl($url);
        if (!$parsed) {
            return false;
        }

        return $this->projectExists($parsed["host"], $parsed["projectId"]);
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
        $projectId = urlencode("$namespace/$project");

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


    public function request(string $url)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $result = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        // Erreur réseau (DNS, timeout, etc.)
        if ($curlError) {
            throw new \RuntimeException("Erreur cURL : $curlError");
        }

        // Erreur HTTP GitLab (403, 404, 500, etc.)
        if ($httpCode >= 400) {
            throw new \RuntimeException("Erreur GitLab API HTTP $httpCode pour $url");
        }

        if (!$result) {
            return null;
        }

        $decoded = json_decode($result, true);

        return $decoded === null ? $result : $decoded;
    }

    public function buildTree(array $items): array
    {
        $tree = [];

        foreach ($items as $item) {
            // Validation minimale
            if (!isset($item['path']) || !isset($item['type'])) {
                // skip or throw depending on your policy
                continue;
            }

            $parts = explode('/', $item['path']);
            $current = &$tree;

            foreach ($parts as $index => $part) {
                $isLast = ($index === count($parts) - 1);

                if ($isLast) {
                    // Si déjà existant et contient des enfants, on doit MERGER intelligemment
                    if (isset($current[$part]) && isset($current[$part]['children'])) {
                        // cas où on a d'abord créé le dossier via un chemin enfant
                        // on garde les children et on complète les métadonnées
                        $current[$part]['type'] = $item['type'];
                        $current[$part]['path'] = $item['path'];
                    } else {
                        // création normale de la feuille
                        $current[$part] = [
                            'type' => $item['type'],
                            'path' => $item['path']
                        ];
                    }
                } else {
                    // Créer le dossier s’il n'existe pas ou s'il a été créé comme feuille -> convertir en dossier
                    if (!isset($current[$part])) {
                        $current[$part] = [
                            'type' => 'tree',
                            'children' => []
                        ];
                    } elseif (!isset($current[$part]['children'])) {
                        // Si l'entrée existe mais n'a pas 'children', on la convertit en dossier en préservant le path si besoin
                        $existing = $current[$part];
                        $current[$part] = [
                            'type' => 'tree',
                            'path' => $existing['path'] ?? null,
                            'children' => []
                        ];
                    }

                    // Descendre
                    $current = &$current[$part]['children'];
                }
            }

            // Important : détruit la référence pour éviter effets de bord
            unset($current);
        }

        // Optionnel : trier récursivement (dossiers avant fichiers, alphabétique)
        $this->sortTree($tree);

        return $tree;
    }

    private function sortTree(array &$nodes): void
    {
        // Recurse on children and sort keys
        foreach ($nodes as $key => &$node) {
            if (isset($node['children']) && is_array($node['children'])) {
                $this->sortTree($node['children']);
            }
        }
        unset($node);

        // Sort so that folders appear before files, then alphabetical by key
        uasort($nodes, function ($a, $b) {
            $isTreeA = ($a['type'] ?? '') === 'tree';
            $isTreeB = ($b['type'] ?? '') === 'tree';

            if ($isTreeA !== $isTreeB) {
                return $isTreeA ? -1 : 1; // tree first
            }

            // fallback on key names: we don't have keys here, so this comparator will preserve insertion order.
            return 0;
        });
    }
}

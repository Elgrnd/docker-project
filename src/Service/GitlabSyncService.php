<?php

namespace App\Service;

use App\Entity\BinaryFile;
use App\Entity\File;
use App\Entity\TextFile;
use App\Entity\Utilisateur;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\MimeTypes;

final class GitlabSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileRepository $fileRepo,
        private readonly GitlabApiService $gitlab,
        private readonly GitlabTokenCryptoService $crypto,
        private readonly string $storageDir,
    ) {}

    public function getLatestShaForUser(Utilisateur $u): ?string
    {
        $parsed = $this->gitlab->parseGitlabUrl((string) $u->getGitlabUrl());
        if (!$parsed) return null;

        $token = $this->crypto->decrypt($u->getGitlabTokenCipher(), $u->getGitlabTokenNonce());

        return $this->gitlab->getLatestCommitSha(
            $parsed['host'],
            $parsed['projectId'],
            $parsed['branch'],
            $token
        );
    }

    public function buildTreeFromDatabase(Utilisateur $u): array
    {
        $files = $this->fileRepo->findFromGitlabByUser($u);

        $parsed = $this->gitlab->parseGitlabUrl((string) $u->getGitlabUrl());
        $project = $parsed['project'] ?? 'GitLab';

        $branch = $parsed['branch'] ?? 'HEAD';
        $rootPrefix = sprintf('Dépôt GitLab : %s (branche : %s)', $project, $branch);

        $leafs = [];
        foreach ($files as $f) {
            if (!$f instanceof File) continue;

            $path = $f->getGitlabPath();
            if (!$path) continue;

            $displayPath = $rootPrefix . '/' . ltrim($path, '/');

            $leaf = [
                'path' => $displayPath,
                'id' => $f->getId(),
                'name' => basename($path),
                'isText' => $f->isText(),
                'isYaml' => $f->isYaml(),
                'body' => null,
            ];

            if ($f instanceof TextFile) {
                $leaf['body'] = $f->getBodyFile();
            }

            $leafs[] = $leaf;
        }

        return $this->buildTreeFromLeafs($leafs);
    }

    public function syncUtilisateur(Utilisateur $u, int $maxBytes = 10485760): array
    {
        $parsed = $this->gitlab->parseGitlabUrl((string) $u->getGitlabUrl());
        if (!$parsed) {
            throw new \RuntimeException("URL GitLab invalide");
        }

        $token = $this->crypto->decrypt($u->getGitlabTokenCipher(), $u->getGitlabTokenNonce());

        $sha = $this->gitlab->getLatestCommitSha($parsed['host'], $parsed['projectId'], $parsed['branch'], $token);

        $items = $this->gitlab->listRepositoryTree($parsed['host'], $parsed['projectId'], $parsed['branch'], $token);

        $this->deleteAllFromGitlabFiles($u);

        $mimeTypes = MimeTypes::getDefault();

        $imported = 0;
        $ignoredTooBig = 0;
        $ignoredNotAllowed = 0;

        foreach ($items as $item) {
            if (!is_array($item) || ($item['type'] ?? null) !== 'blob') continue;

            $path = $item['path'] ?? null;
            if (!is_string($path) || $path === '') continue;

            $nameFile = basename($path);
            $ext = strtolower(ltrim((string) pathinfo($nameFile, PATHINFO_EXTENSION), '.'));
            if ($ext === '') { $ignoredNotAllowed++; continue; }

            $rawUrl = "https://{$parsed['host']}/api/v4/projects/{$parsed['projectId']}/repository/files/"
                . rawurlencode($path) . "/raw?ref=" . rawurlencode($parsed['branch']);

            try {
                $raw = $this->gitlab->request($rawUrl, $token);
            } catch (\Throwable $e) {
                continue;
            }

            if (!is_string($raw) || $raw === '') continue;

            $rawSize = strlen($raw);
            if ($rawSize > $maxBytes) { $ignoredTooBig++; continue; }

            $guessedMime = $mimeTypes->getMimeTypes($ext)[0] ?? 'application/octet-stream';

            $file = null;

            if (in_array($ext, TextFile::allowedExtensions(), true)) {
                $tf = new TextFile();
                $tf->setBodyFile($raw);
                $file = $tf;
            } elseif (in_array($ext, BinaryFile::allowedExtensions(), true)) {
                $bf = new BinaryFile();
                $bf->setSize($rawSize);

                $relPath = $this->storeGitlabBinary($u->getId(), $sha ?? 'no_sha', $path, $raw, $ext);
                $bf->setStoragePath($relPath);

                $file = $bf;
            } else {
                $ignoredNotAllowed++;
                continue;
            }

            $file->setNameFile($nameFile);
            $file->setExtension($ext);
            $file->setMimeType($guessedMime);
            $file->setUtilisateurFile($u);
            $file->setFromGitlab(true);
            $file->setGitlabPath($path);

            try {
                $file->assertValidExtension($ext);
                $file->assertValidMimeType($guessedMime);
            } catch (\DomainException $e) {
                if ($file instanceof BinaryFile) {
                    $this->deleteBinaryIfExists($file);
                }
                $ignoredNotAllowed++;
                continue;
            }

            $this->em->persist($file);
            $imported++;
        }

        $u->setGitlabLastCommitSha($sha);
        $this->em->persist($u);
        $this->em->flush();

        return [
            'imported' => $imported,
            'ignoredTooBig' => $ignoredTooBig,
            'ignoredNotAllowed' => $ignoredNotAllowed,
            'sha' => $sha,
        ];
    }

    public function deleteAllFromGitlabFiles(Utilisateur $u): void
    {
        $files = $this->fileRepo->findFromGitlabByUser($u);

        foreach ($files as $f) {
            if ($f instanceof BinaryFile) {
                $this->deleteBinaryIfExists($f);
            }
            $this->em->remove($f);
        }
        $this->em->flush();
    }

    public function cloneFromGitlabToUserSpace(File $src, Utilisateur $u): File
    {
        if (!$src->isFromGitlab()) {
            throw new \RuntimeException("Source non GitLab");
        }

        if ($src instanceof TextFile) {
            $dst = new TextFile();
            $dst->setBodyFile((string) $src->getBodyFile());
        } elseif ($src instanceof BinaryFile) {
            $dst = new BinaryFile();
            $dst->setSize($src->getSize());

            $newRel = $this->copyBinary($src);
            $dst->setStoragePath($newRel);
        } else {
            throw new \RuntimeException("Type de fichier non supporté");
        }

        $dst->setNameFile((string) $src->getNameFile());
        $dst->setExtension($src->getExtension());
        $dst->setMimeType($src->getMimeType());
        $dst->setDescription($src->getDescription());

        $dst->setUtilisateurFile($u);
        $dst->setFromGitlab(false);
        $dst->setGitlabPath(null);

        return $dst;
    }

    private function storeGitlabBinary(int $userId, string $sha, string $gitlabPath, string $bytes, string $ext): string
    {
        $destDir = rtrim($this->storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'gitlab_cache';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $extension = strtolower(ltrim($ext, '.'));

        $safeName = bin2hex(random_bytes(16));
        $finalName = $safeName . ($extension !== '' ? '.' . $extension : '');

        $full = $destDir . DIRECTORY_SEPARATOR . $finalName;

        if (file_put_contents($full, $bytes) === false) {
            throw new \RuntimeException("Impossible d'écrire le binaire GitLab dans le storage.");
        }

        return 'gitlab_cache/' . $finalName;
    }


    private function deleteBinaryIfExists(BinaryFile $bf): void
    {
        $rel = (string) $bf->getStoragePath();
        if ($rel === '') return;

        $full = rtrim($this->storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($rel, DIRECTORY_SEPARATOR);
        if (is_file($full)) @unlink($full);
    }

    private function copyBinary(BinaryFile $src): string
    {
        $srcRel = (string) $src->getStoragePath();
        $srcFull = rtrim($this->storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($srcRel, DIRECTORY_SEPARATOR);

        if (!is_file($srcFull)) {
            throw new \RuntimeException("Binaire introuvable sur disque");
        }

        $destDir = rtrim($this->storageDir, DIRECTORY_SEPARATOR);

        $extension = strtolower(ltrim((string) pathinfo((string) $src->getNameFile(), PATHINFO_EXTENSION), '.'));

        $safeName = bin2hex(random_bytes(16));
        $finalName = $safeName . ($extension !== '' ? '.' . $extension : '');

        $destFull = $destDir . DIRECTORY_SEPARATOR . $finalName;

        if (!@copy($srcFull, $destFull)) {
            throw new \RuntimeException("Impossible de copier le fichier importé dans le storage.");
        }

        return $finalName;
    }


    private function buildTreeFromLeafs(array $leafs): array
    {
        $tree = [];

        foreach ($leafs as $leaf) {
            $parts = explode('/', $leaf['path']);
            $current = &$tree;

            foreach ($parts as $i => $part) {
                $isLast = ($i === count($parts) - 1);

                if ($isLast) {
                    $current[$part] = [
                        'type' => 'blob',
                        'path' => $leaf['path'],
                        'id' => $leaf['id'],
                        'name' => $leaf['name'],
                        'isText' => $leaf['isText'],
                        'isYaml' => $leaf['isYaml'] ?? false,
                        'body' => $leaf['body'],
                    ];
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = ['type' => 'tree', 'children' => []];
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
        foreach ($nodes as &$node) {
            if (isset($node['children']) && is_array($node['children'])) {
                $this->sortTree($node['children']);
            }
        }
        unset($node);

        uasort($nodes, function ($a, $b) {
            $isTreeA = ($a['type'] ?? '') === 'tree';
            $isTreeB = ($b['type'] ?? '') === 'tree';
            if ($isTreeA !== $isTreeB) return $isTreeA ? -1 : 1;
            return 0;
        });
    }
}

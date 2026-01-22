<?php

namespace App\Service;

use RuntimeException;

class GitlabTokenCryptoService
{
    private string $key; // 32 bytes

    public function __construct(string $gitlabTokenKey)
    {
        if (!str_starts_with($gitlabTokenKey, 'base64:')) {
            throw new RuntimeException('GITLAB_TOKEN_KEY doit commencer par "base64:"');
        }

        $raw = base64_decode(substr($gitlabTokenKey, 7), true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('GITLAB_TOKEN_KEY invalide (doit être 32 bytes en base64).');
        }

        $this->key = $raw;
    }

    /**
     * @return array{cipher:string, nonce:string} base64
     */
    public function encrypt(string $plainToken): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherRaw = sodium_crypto_secretbox($plainToken, $nonce, $this->key);

        return [
            'cipher' => base64_encode($cipherRaw),
            'nonce' => base64_encode($nonce),
        ];
    }

    public function decrypt(?string $cipherB64, ?string $nonceB64): ?string
    {
        if (!$cipherB64 || !$nonceB64) {
            return null;
        }

        $cipherRaw = base64_decode($cipherB64, true);
        $nonce = base64_decode($nonceB64, true);

        if ($cipherRaw === false || $nonce === false) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open($cipherRaw, $nonce, $this->key);

        return $plain === false ? null : $plain;
    }
}

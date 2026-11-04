<?php

namespace App\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seals the model's key at rest with libsodium.
 */
final readonly class Vault
{
    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->key = sodium_crypto_generichash('sparring-vault|'.$secret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function seal(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function open(string $sealed): string
    {
        $raw = base64_decode($sealed, true) ?: '';
        $plain = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        if (false === $plain) {
            throw new \RuntimeException('Ce secret a été scellé avec une autre clé (APP_SECRET a changé ?).');
        }

        return $plain;
    }
}

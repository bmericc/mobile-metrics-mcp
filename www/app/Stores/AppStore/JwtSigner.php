<?php

namespace App\Stores\AppStore;

use App\Models\StoreAccount;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

/**
 * App Store Connect API için ES256 JWT üretir. Apple en fazla 20 dakikalık token kabul eder.
 */
class JwtSigner
{
    public function token(StoreAccount $account): string
    {
        $credentials = $account->credentials;

        $config = Configuration::forSymmetricSigner(
            new Sha256,
            InMemory::plainText($credentials['private_key']),
        );

        $now = new DateTimeImmutable;

        return $config->builder()
            ->issuedBy($credentials['issuer_id'])
            ->permittedFor('appstoreconnect-v1')
            ->issuedAt($now)
            ->expiresAt($now->modify('+15 minutes'))
            ->withHeader('kid', $credentials['key_id'])
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }
}

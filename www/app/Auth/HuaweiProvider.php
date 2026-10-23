<?php

namespace App\Auth;

use Laravel\Socialite\Two\User;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;

/**
 * Huawei ID (OpenID Connect) için Socialite provider. Hazır bir SocialiteProviders paketi yok.
 */
class HuaweiProvider extends AbstractProvider
{
    public const IDENTIFIER = 'HUAWEI';

    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://oauth-login.cloud.huawei.com/oauth2/v3/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://oauth-login.cloud.huawei.com/oauth2/v3/token';
    }

    /**
     * id_token token endpoint'inden TLS üzerinden, client secret ile doğrudan alındığı için
     * imza doğrulaması yerine TLS sunucu doğrulamasına güveniliyor (OIDC Core 3.1.3.7).
     */
    protected function getUserByToken($token): array
    {
        $idToken = $this->credentialsResponseBody['id_token'] ?? null;

        if (! is_string($idToken)) {
            return [];
        }

        $payload = explode('.', $idToken)[1] ?? '';

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true) ?: [];
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? null,
            'name' => $user['display_name'] ?? $user['nickname'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }
}

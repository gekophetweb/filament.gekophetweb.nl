<?php

namespace App\Support\Sso;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use RuntimeException;

/**
 * Generic OpenID Connect provider (Keycloak, Authentik, Entra ID, Google, ...),
 * configured from the issuer's discovery document.
 */
class OidcProvider extends AbstractProvider
{
    protected $scopes = ['openid', 'email', 'profile'];

    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    protected string $issuer = '';

    public function setIssuer(string $issuer): static
    {
        $this->issuer = rtrim($issuer, '/');

        return $this;
    }

    /** @return array<string, mixed> */
    protected function discovery(): array
    {
        if ($this->issuer === '') {
            throw new RuntimeException('SSO_ISSUER is not configured.');
        }

        return Cache::remember('oidc:discovery:'.sha1($this->issuer), now()->addHour(), function () {
            $response = $this->getHttpClient()->get($this->issuer.'/.well-known/openid-configuration');

            return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        });
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->discovery()['authorization_endpoint'], $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->discovery()['token_endpoint'];
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->discovery()['userinfo_endpoint'], [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'],
        ]);

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'],
            'name' => Arr::get($user, 'name', Arr::get($user, 'preferred_username')),
            'email' => Arr::get($user, 'email'),
            'nickname' => Arr::get($user, 'preferred_username'),
        ]);
    }
}

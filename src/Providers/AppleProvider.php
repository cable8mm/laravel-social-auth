<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Providers;

use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Verifiers\AppleTokenVerifier;
use Illuminate\Support\Facades\Session;

final class AppleProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'apple';
    }

    public function verify(array $payload): ProviderUser
    {
        $this->assertValidState($payload);

        $code = $payload['code'] ?? null;
        $identityToken = $payload['id_token'] ?? null;
        if (! is_string($code) || $code === '' || ! is_string($identityToken) || $identityToken === '') {
            throw SocialAuthException::verificationFailed('apple', 'Missing authorization response');
        }

        $verifier = $this->verifier();
        $claims = $verifier->verify(
            $identityToken,
            Session::pull(config('social-auth.session.nonce', 'social_auth.nonce')),
        );
        $tokens = $verifier->exchangeCode($code);

        $name = null;
        if (isset($payload['user']) && is_string($payload['user'])) {
            $user = json_decode($payload['user'], true);
            if (is_array($user)) {
                $nameData = $user['name'] ?? [];
                if (is_array($nameData)) {
                    $name = trim(implode(' ', array_filter([
                        $nameData['firstName'] ?? null,
                        $nameData['lastName'] ?? null,
                    ])));
                }
            }
        }

        return new ProviderUser(
            provider: $this->getName(),
            providerId: $claims['sub'],
            email: isset($claims['email']) && is_string($claims['email']) ? $claims['email'] : null,
            emailVerified: filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            name: $name !== '' ? $name : null,
            accessToken: isset($tokens['access_token']) && is_string($tokens['access_token']) ? $tokens['access_token'] : null,
            refreshToken: isset($tokens['refresh_token']) && is_string($tokens['refresh_token']) ? $tokens['refresh_token'] : null,
            tokenExpiresAt: isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            raw: $this->sanitizeRaw($claims),
        );
    }

    public function revoke(?string $accessToken): bool
    {
        return $accessToken !== null && $accessToken !== ''
            ? $this->verifier()->revoke($accessToken)
            : false;
    }

    private function verifier(): AppleTokenVerifier
    {
        return new AppleTokenVerifier(
            (string) $this->config['client_id'],
            (string) $this->config['team_id'],
            (string) $this->config['key_id'],
            (string) $this->config['private_key'],
            $this->config['redirect'] ?? null,
        );
    }

    private function assertValidState(array $payload): void
    {
        $expected = Session::pull(config('social-auth.session.state', 'social_auth.state'));
        $state = $payload['state'] ?? null;

        if ($expected === null || ! is_string($state) || ! hash_equals((string) $expected, $state)) {
            throw SocialAuthException::invalidState();
        }
    }
}

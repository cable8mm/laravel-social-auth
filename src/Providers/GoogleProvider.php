<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Providers;

use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Verifiers\GoogleCredentialVerifier;
use Illuminate\Support\Facades\Session;

class GoogleProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'google';
    }

    public function verify(array $payload): ProviderUser
    {
        $credential = $payload['credential'] ?? null;
        if (empty($credential) || ! is_string($credential)) {
            throw SocialAuthException::verificationFailed('google', 'Missing credential');
        }

        $sessionKey = config('social-auth.session.nonce', 'social_auth.nonce');
        $expectedNonce = Session::get($sessionKey);
        Session::forget($sessionKey);

        $clientId = $this->getClientId();
        if (empty($clientId)) {
            throw SocialAuthException::invalidConfiguration('Google client_id is required');
        }

        $verifier = new GoogleCredentialVerifier($clientId);
        $claims = $verifier->verify($credential, $expectedNonce);

        $email = $claims['email'] ?? null;
        $emailVerified = (bool) ($claims['email_verified'] ?? false);

        return new ProviderUser(
            provider: $this->getName(),
            providerId: (string) $claims['sub'],
            email: is_string($email) ? $email : null,
            emailVerified: $emailVerified && is_string($email),
            name: isset($claims['name']) && is_string($claims['name']) ? $claims['name'] : null,
            nickname: null,
            avatar: isset($claims['picture']) && is_string($claims['picture']) ? $claims['picture'] : null,
            accessToken: null,
            refreshToken: null,
            tokenExpiresAt: null,
            raw: $this->sanitizeRaw($claims),
        );
    }
}

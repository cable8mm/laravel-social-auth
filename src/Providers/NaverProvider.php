<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Providers;

use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Verifiers\NaverTokenVerifier;
use Illuminate\Support\Facades\Session;

class NaverProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'naver';
    }

    public function verify(array $payload): ProviderUser
    {
        $this->assertValidState($payload);

        $accessToken = $payload['access_token'] ?? null;
        if (empty($accessToken) || ! is_string($accessToken)) {
            throw SocialAuthException::verificationFailed('naver', 'Missing access token');
        }

        $verifier = new NaverTokenVerifier;
        $profile = $verifier->fetchProfile($accessToken);

        $email = $profile['email'] ?? null;
        // Naver only returns verified emails
        $emailVerified = is_string($email) && $email !== '';

        return new ProviderUser(
            provider: $this->getName(),
            providerId: (string) $profile['id'],
            email: $emailVerified ? $email : null,
            emailVerified: $emailVerified,
            name: isset($profile['name']) && is_string($profile['name']) ? $profile['name'] : null,
            nickname: isset($profile['nickname']) && is_string($profile['nickname']) ? $profile['nickname'] : null,
            avatar: isset($profile['profile_image']) && is_string($profile['profile_image']) ? $profile['profile_image'] : null,
            accessToken: $accessToken,
            refreshToken: $payload['refresh_token'] ?? null,
            tokenExpiresAt: isset($payload['expires_in'])
                ? now()->addSeconds((int) $payload['expires_in'])
                : null,
            raw: $this->sanitizeRaw($profile),
        );
    }

    public function withProviderConsent(ProviderUser $providerUser): ProviderUser
    {
        if (! (bool) config('social-auth.consent.providers.naver.enabled', false)) {
            return $providerUser;
        }

        if ($providerUser->accessToken === null || $providerUser->accessToken === '') {
            throw SocialAuthException::verificationFailed('naver', 'Missing access token for service agreements');
        }

        $agreements = (new NaverTokenVerifier)->fetchAgreement($providerUser->accessToken);

        return $providerUser->withConsents($agreements);
    }

    public function revoke(?string $accessToken): bool
    {
        if (empty($accessToken)) {
            return false;
        }

        $clientId = $this->getClientId();
        $clientSecret = $this->config['client_secret'] ?? null;
        if (empty($clientId) || empty($clientSecret)) {
            return false;
        }

        $verifier = new NaverTokenVerifier;

        return $verifier->revoke($accessToken, $clientId, $clientSecret);
    }

    private function assertValidState(array $payload): void
    {
        $state = $payload['state'] ?? null;
        $sessionKey = config('social-auth.session.state', 'social_auth.state');
        $expected = Session::get($sessionKey);
        Session::forget($sessionKey);

        if ($expected === null || $state === null || ! hash_equals((string) $expected, (string) $state)) {
            throw SocialAuthException::invalidState();
        }
    }
}

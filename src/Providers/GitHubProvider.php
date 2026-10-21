<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Providers;

use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Verifiers\GitHubTokenVerifier;
use Illuminate\Support\Facades\Session;

final class GitHubProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'github';
    }

    public function verify(array $payload): ProviderUser
    {
        $this->assertValidState($payload);

        $code = $payload['code'] ?? null;
        if (! is_string($code) || $code === '') {
            throw SocialAuthException::verificationFailed('github', 'Missing authorization code');
        }

        $result = $this->verifier()->exchangeCode($code);
        $tokens = $result['tokens'];
        $profile = $result['profile'];
        $email = $result['email'];

        $name = $profile['name'] ?? null;
        $avatar = $profile['avatar_url'] ?? null;
        $login = $profile['login'] ?? null;

        return new ProviderUser(
            provider: $this->getName(),
            providerId: (string) $profile['id'],
            email: $email,
            emailVerified: $email !== null,
            name: is_string($name) && $name !== '' ? $name : null,
            nickname: is_string($login) ? $login : null,
            avatar: is_string($avatar) ? $avatar : null,
            accessToken: $tokens['access_token'] ?? null,
            refreshToken: $tokens['refresh_token'] ?? null,
            tokenExpiresAt: isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            raw: $this->sanitizeRaw($profile),
        );
    }

    public function revoke(?string $accessToken): bool
    {
        if ($accessToken === null || $accessToken === '' || empty($this->config['client_secret'])) {
            return false;
        }

        return $this->verifier()->revoke($accessToken);
    }

    private function verifier(): GitHubTokenVerifier
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->config['client_secret'] ?? null;
        if (empty($clientId) || empty($clientSecret)) {
            throw SocialAuthException::invalidConfiguration('GitHub client_id and client_secret are required');
        }

        return new GitHubTokenVerifier(
            (string) $clientId,
            (string) $clientSecret,
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

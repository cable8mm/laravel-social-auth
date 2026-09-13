<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Providers;

use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Verifiers\KakaoTokenVerifier;
use Illuminate\Support\Facades\Session;

class KakaoProvider extends AbstractProvider
{
    public function getName(): string
    {
        return 'kakao';
    }

    public function verify(array $payload): ProviderUser
    {
        $this->assertValidState($payload);

        $code = $payload['code'] ?? null;
        if (empty($code) || ! is_string($code)) {
            throw SocialAuthException::verificationFailed('kakao', 'Missing authorization code');
        }

        $clientId = $this->getClientId();
        if (empty($clientId)) {
            throw SocialAuthException::invalidConfiguration('Kakao client_id is required');
        }

        $verifier = new KakaoTokenVerifier(
            $clientId,
            $this->config['client_secret'] ?? null,
            $this->config['redirect'] ?? null,
        );

        $result = $verifier->exchangeCode($code);
        $tokens = $result['tokens'];
        $profile = $result['profile'];

        $kakaoAccount = $profile['kakao_account'] ?? [];
        $email = $kakaoAccount['email'] ?? null;
        $isEmailVerified = (bool) ($kakaoAccount['is_email_verified'] ?? false);
        $isEmailValid = (bool) ($kakaoAccount['is_email_valid'] ?? false);

        $properties = $profile['properties'] ?? [];
        $nickname = $properties['nickname'] ?? ($kakaoAccount['profile']['nickname'] ?? null);
        $avatar = $properties['profile_image'] ?? ($kakaoAccount['profile']['profile_image_url'] ?? null);

        $expiresIn = isset($tokens['expires_in']) ? (int) $tokens['expires_in'] : null;
        $expiresAt = $expiresIn ? now()->addSeconds($expiresIn) : null;

        return new ProviderUser(
            provider: $this->getName(),
            providerId: (string) $profile['id'],
            email: is_string($email) ? $email : null,
            emailVerified: $isEmailVerified && $isEmailValid && is_string($email),
            name: null, // Kakao name is intentionally not mapped by default
            nickname: is_string($nickname) ? $nickname : null,
            avatar: is_string($avatar) ? $avatar : null,
            accessToken: $tokens['access_token'] ?? null,
            refreshToken: $tokens['refresh_token'] ?? null,
            tokenExpiresAt: $expiresAt,
            raw: $this->sanitizeRaw(array_merge($profile, ['token_type' => $tokens['token_type'] ?? null])),
        );
    }

    public function revoke(?string $accessToken): bool
    {
        if (empty($accessToken)) {
            return false;
        }

        $clientId = $this->getClientId();
        if (empty($clientId)) {
            return false;
        }

        $verifier = new KakaoTokenVerifier(
            $clientId,
            $this->config['client_secret'] ?? null,
            $this->config['redirect'] ?? null,
        );

        return $verifier->revoke($accessToken);
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

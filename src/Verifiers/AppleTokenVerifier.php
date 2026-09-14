<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class AppleTokenVerifier
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';

    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    private const REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    public function __construct(
        private readonly string $clientId,
        private readonly string $teamId,
        private readonly string $keyId,
        private readonly string $privateKey,
        private readonly ?string $redirectUri = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code): array
    {
        $payload = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        if ($this->redirectUri !== null) {
            $payload['redirect_uri'] = $this->redirectUri;
        }

        $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, $payload);

        if (! $response->successful() || ! is_array($response->json())) {
            throw SocialAuthException::verificationFailed('apple', 'Authorization code exchange failed');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $identityToken, ?string $expectedNonce = null): array
    {
        try {
            $claims = (array) JWT::decode($identityToken, JWK::parseKeySet($this->jwks()));
        } catch (\Throwable) {
            throw SocialAuthException::verificationFailed('apple', 'JWT signature or format invalid');
        }

        if (($claims['iss'] ?? null) !== 'https://appleid.apple.com') {
            throw SocialAuthException::verificationFailed('apple', 'Invalid issuer');
        }

        if (($claims['aud'] ?? null) !== $this->clientId) {
            throw SocialAuthException::verificationFailed('apple', 'Invalid audience');
        }

        if (! isset($claims['exp']) || (int) $claims['exp'] < time()) {
            throw SocialAuthException::verificationFailed('apple', 'Token expired');
        }

        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw SocialAuthException::verificationFailed('apple', 'Missing subject');
        }

        if ($expectedNonce !== null && ! hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
            throw SocialAuthException::invalidNonce();
        }

        return $claims;
    }

    public function revoke(string $accessToken): bool
    {
        return Http::asForm()->timeout(10)->post(self::REVOKE_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret(),
            'token' => $accessToken,
            'token_type_hint' => 'access_token',
        ])->successful();
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(): array
    {
        return Cache::remember('social_auth.apple_jwks', 3600, function () {
            $response = Http::timeout(10)->get(self::JWKS_URL);

            if (! $response->successful() || ! is_array($response->json())) {
                throw SocialAuthException::verificationFailed('apple', 'Unable to fetch Apple JWKS');
            }

            return $response->json();
        });
    }

    private function clientSecret(): string
    {
        $key = str_replace('\\n', "\n", $this->privateKey);

        try {
            return JWT::encode([
                'iss' => $this->teamId,
                'iat' => time(),
                'exp' => time() + 86400 * 180,
                'aud' => 'https://appleid.apple.com',
                'sub' => $this->clientId,
            ], $key, 'ES256', $this->keyId);
        } catch (\Throwable) {
            throw SocialAuthException::invalidConfiguration('Apple private key is invalid');
        }
    }
}

<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleCredentialVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ISSUERS = [
        'https://accounts.google.com',
        'accounts.google.com',
    ];

    public function __construct(
        private readonly string $clientId,
    ) {}

    /**
     * Verify Google Identity Services credential JWT.
     *
     * @return array<string, mixed> Decoded claims
     *
     * @throws SocialAuthException
     */
    public function verify(string $credential, ?string $expectedNonce = null): array
    {
        try {
            $keys = $this->getJwks();
            $decoded = JWT::decode($credential, JWK::parseKeySet($keys));
            $claims = (array) $decoded;
        } catch (\Throwable $e) {
            throw SocialAuthException::verificationFailed('google', 'JWT signature or format invalid');
        }

        $this->assertIssuer($claims);
        $this->assertAudience($claims);
        $this->assertExpiration($claims);
        $this->assertSubject($claims);

        if ($expectedNonce !== null) {
            $this->assertNonce($claims, $expectedNonce);
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJwks(): array
    {
        return Cache::remember('social_auth.google_jwks', 3600, function () {
            $response = Http::timeout(10)->get(self::JWKS_URL);

            if (! $response->successful()) {
                throw SocialAuthException::verificationFailed('google', 'Unable to fetch Google JWKS');
            }

            return $response->json();
        });
    }

    private function assertIssuer(array $claims): void
    {
        $iss = $claims['iss'] ?? null;
        if (! in_array($iss, self::ISSUERS, true)) {
            throw SocialAuthException::verificationFailed('google', 'Invalid issuer');
        }
    }

    private function assertAudience(array $claims): void
    {
        $aud = $claims['aud'] ?? null;
        if ($aud !== $this->clientId) {
            throw SocialAuthException::verificationFailed('google', 'Invalid audience');
        }
    }

    private function assertExpiration(array $claims): void
    {
        $exp = $claims['exp'] ?? null;
        if ($exp === null || $exp < time()) {
            throw SocialAuthException::verificationFailed('google', 'Token expired');
        }
    }

    private function assertSubject(array $claims): void
    {
        $sub = $claims['sub'] ?? null;
        if (empty($sub) || ! is_string($sub)) {
            throw SocialAuthException::verificationFailed('google', 'Missing subject');
        }
    }

    private function assertNonce(array $claims, string $expectedNonce): void
    {
        $nonce = $claims['nonce'] ?? null;
        if ($nonce === null || ! hash_equals($expectedNonce, (string) $nonce)) {
            throw SocialAuthException::invalidNonce();
        }
    }
}

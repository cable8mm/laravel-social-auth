<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleRiscWebhookVerifier
{
    private const ISSUER = 'https://accounts.google.com';

    public function __construct(
        private readonly string $clientId,
    ) {}

    /** @return array<string, mixed> */
    public function verify(string $set): array
    {
        try {
            $claims = (array) JWT::decode($set, JWK::parseKeySet($this->getJwks()));
        } catch (\Throwable) {
            throw SocialAuthException::verificationFailed('google', 'RISC SET is invalid');
        }

        if (($claims['iss'] ?? null) !== self::ISSUER) {
            throw SocialAuthException::verificationFailed('google', 'Invalid RISC issuer');
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        if (! in_array($this->clientId, $audiences, true)) {
            throw SocialAuthException::verificationFailed('google', 'Invalid RISC audience');
        }

        if (! is_string($claims['jti'] ?? null) || $claims['jti'] === '') {
            throw SocialAuthException::verificationFailed('google', 'Missing RISC event ID');
        }

        if (! is_object($claims['events'] ?? null) && ! is_array($claims['events'] ?? null)) {
            throw SocialAuthException::verificationFailed('google', 'Missing RISC events');
        }

        $events = [];
        foreach ((array) $claims['events'] as $eventType => $event) {
            $event = (array) $event;
            if (isset($event['subject'])) {
                $event['subject'] = (array) $event['subject'];
            }
            $events[$eventType] = $event;
        }
        $claims['events'] = $events;

        return $claims;
    }

    /** @return array<string, mixed> */
    private function getJwks(): array
    {
        return Cache::remember('social_auth.google_risc_jwks', 3600, function (): array {
            $url = (string) config(
                'social-auth.webhooks.google.jwks_url',
                'https://www.googleapis.com/oauth2/v3/certs',
            );
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                throw SocialAuthException::verificationFailed('google', 'Unable to fetch RISC JWKS');
            }

            return $response->json();
        });
    }
}

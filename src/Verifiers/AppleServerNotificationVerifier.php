<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class AppleServerNotificationVerifier
{
    private const ISSUER = 'https://appleid.apple.com';

    /** @var list<string> */
    private const EVENT_TYPES = [
        'email-enabled',
        'email-disabled',
        'consent-revoked',
        'account-deleted',
    ];

    public function __construct(
        private readonly string $clientId,
    ) {}

    /**
     * @return array{event: array<string, mixed>, claims: array<string, mixed>}
     */
    public function verify(string $payload): array
    {
        $body = json_decode($payload, true);
        $signedPayload = is_array($body) ? ($body['signedPayload'] ?? null) : null;

        if (! is_string($signedPayload) || $signedPayload === '') {
            throw SocialAuthException::verificationFailed('apple', 'Missing signed notification');
        }

        try {
            $claims = (array) JWT::decode($signedPayload, JWK::parseKeySet($this->jwks()));
        } catch (\Throwable) {
            throw SocialAuthException::verificationFailed('apple', 'Apple notification is invalid');
        }

        if (($claims['iss'] ?? null) !== self::ISSUER) {
            throw SocialAuthException::verificationFailed('apple', 'Invalid Apple notification issuer');
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        if (! in_array($this->clientId, $audiences, true)) {
            throw SocialAuthException::verificationFailed('apple', 'Invalid Apple notification audience');
        }

        $event = $claims['events'] ?? null;
        if (is_object($event)) {
            $event = (array) $event;
        }
        if (! is_array($event)) {
            throw SocialAuthException::verificationFailed('apple', 'Missing Apple notification event');
        }

        $eventType = $event['type'] ?? null;
        $providerId = $event['sub'] ?? null;
        if (! is_string($eventType) || ! in_array($eventType, self::EVENT_TYPES, true)) {
            throw SocialAuthException::verificationFailed('apple', 'Unknown Apple notification event');
        }
        if (! is_string($providerId) || $providerId === '') {
            throw SocialAuthException::verificationFailed('apple', 'Missing Apple notification subject');
        }

        return [
            'event' => $event,
            'claims' => $claims,
        ];
    }

    /** @return array<string, mixed> */
    private function jwks(): array
    {
        return Cache::remember('social_auth.apple_server_notifications_jwks', 3600, function (): array {
            $url = (string) config(
                'social-auth.webhooks.apple.jwks_url',
                'https://appleid.apple.com/auth/keys',
            );
            $response = Http::timeout(10)->get($url);

            if (! $response->successful() || ! is_array($response->json())) {
                throw SocialAuthException::verificationFailed('apple', 'Unable to fetch Apple notification JWKS');
            }

            return $response->json();
        });
    }
}

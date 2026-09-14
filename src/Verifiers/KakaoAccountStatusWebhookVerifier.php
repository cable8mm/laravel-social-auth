<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class KakaoAccountStatusWebhookVerifier
{
    private const ISSUER = 'https://kauth.kakao.com';

    public function __construct(
        private readonly string $restApiKey,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws SocialAuthException
     */
    public function verify(string $set): array
    {
        try {
            $claims = (array) JWT::decode($set, JWK::parseKeySet($this->getJwks()));
        } catch (\Throwable) {
            throw SocialAuthException::verificationFailed('kakao', 'Webhook SET is invalid');
        }

        if (($claims['iss'] ?? null) !== self::ISSUER) {
            throw SocialAuthException::verificationFailed('kakao', 'Invalid webhook issuer');
        }

        if (($claims['aud'] ?? null) !== $this->restApiKey) {
            throw SocialAuthException::verificationFailed('kakao', 'Invalid webhook audience');
        }

        if (! is_string($claims['jti'] ?? null) || $claims['jti'] === '') {
            throw SocialAuthException::verificationFailed('kakao', 'Missing webhook ID');
        }

        if (! is_object($claims['events'] ?? null) && ! is_array($claims['events'] ?? null)) {
            throw SocialAuthException::verificationFailed('kakao', 'Missing webhook events');
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

    /**
     * @return array<string, mixed>
     */
    private function getJwks(): array
    {
        return Cache::remember('social_auth.kakao_webhook_jwks', 3600, function (): array {
            $url = (string) config(
                'social-auth.webhooks.kakao.jwks_url',
                'https://kauth.kakao.com/.well-known/jwks.json',
            );
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                throw SocialAuthException::verificationFailed('kakao', 'Unable to fetch webhook JWKS');
            }

            return $response->json();
        });
    }
}

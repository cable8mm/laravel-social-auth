<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Events\SocialAccountStatusChanged;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class GoogleRiscWebhookTest extends TestCase
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    /** @return array{private: string, n: string, e: string} */
    private function generateKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        return [
            'private' => $privateKey,
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @param array{private: string, n: string, e: string} $key */
    private function makeSet(array $key): string
    {
        Cache::flush();
        Http::fake([
            self::JWKS_URL => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => 'google-risc-test-key',
                    'n' => $key['n'],
                    'e' => $key['e'],
                ]],
            ]),
        ]);

        return JWT::encode([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-google-client-id',
            'iat' => time(),
            'jti' => 'google-risc-test-event',
            'events' => [
                'https://schemas.openid.net/secevent/risc/event-type/sessions-revoked' => [
                    'subject' => [
                        'subject_type' => 'iss-sub',
                        'iss' => 'https://accounts.google.com',
                        'sub' => 'google-user-1',
                    ],
                ],
            ],
        ], $key['private'], 'RS256', 'google-risc-test-key');
    }

    public function test_valid_risc_event_is_dispatched(): void
    {
        Event::fake();
        $set = $this->makeSet($this->generateKeyPair());

        $response = $this->call(
            'POST',
            route('social-auth.webhooks.google'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/secevent+jwt'],
            $set,
        );

        $response->assertStatus(202);
        Event::assertDispatched(function (SocialAccountStatusChanged $event): bool {
            return $event->provider === 'google'
                && $event->providerId === 'google-user-1'
                && $event->eventType === 'https://schemas.openid.net/secevent/risc/event-type/sessions-revoked';
        });
    }

    public function test_invalid_risc_event_is_rejected(): void
    {
        $this->call(
            'POST',
            route('social-auth.webhooks.google'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/secevent+jwt'],
            'invalid-set',
        )->assertStatus(400);
    }
}

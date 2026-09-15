<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Events\SocialAccountStatusChanged;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class AppleServerNotificationTest extends TestCase
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';

    private const CLIENT_ID = 'test-apple-client-id';

    /** @return array{private: string, x: string, y: string} */
    private function generateKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        return [
            'private' => $privateKey,
            // P-256 JWK coordinates are fixed-width 32-byte unsigned values.
            // Some PHP/OpenSSL combinations omit a leading zero byte.
            'x' => $this->base64UrlEncode(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
            'y' => $this->base64UrlEncode(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @param array{private: string, x: string, y: string} $key */
    private function makePayload(array $key, string $eventType, string $providerId): string
    {
        Cache::flush();
        Http::fake([
            self::JWKS_URL => Http::response([
                'keys' => [[
                    'kty' => 'EC',
                    'use' => 'sig',
                    'alg' => 'ES256',
                    'kid' => 'apple-test-key',
                    'crv' => 'P-256',
                    'x' => $key['x'],
                    'y' => $key['y'],
                ]],
            ]),
        ]);

        $signedPayload = JWT::encode([
            'iss' => 'https://appleid.apple.com',
            'aud' => self::CLIENT_ID,
            'iat' => time(),
            'events' => [
                'type' => $eventType,
                'sub' => $providerId,
                'event_timestamp' => (string) (time() * 1000),
            ],
        ], $key['private'], 'ES256', 'apple-test-key');

        return json_encode(['signedPayload' => $signedPayload], JSON_THROW_ON_ERROR);
    }

    public function test_consent_revoked_removes_the_apple_social_account(): void
    {
        config()->set('social-auth.providers.apple.client_id', self::CLIENT_ID);

        $user = User::query()->create(['email' => 'user@example.com']);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_id' => 'apple-user-1',
        ]);

        Event::fake();
        $payload = $this->makePayload($this->generateKeyPair(), 'consent-revoked', 'apple-user-1');

        $this->call(
            'POST',
            route('social-auth.webhooks.apple'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload,
        )->assertOk();

        $this->assertDatabaseMissing('social_accounts', [
            'provider' => 'apple',
            'provider_id' => 'apple-user-1',
        ]);
        Event::assertDispatched(function (SocialAccountStatusChanged $event): bool {
            return $event->provider === 'apple'
                && $event->providerId === 'apple-user-1'
                && $event->eventType === 'consent-revoked';
        });
    }

    public function test_invalid_apple_notification_is_rejected(): void
    {
        config()->set('social-auth.providers.apple.client_id', self::CLIENT_ID);

        $this->postJson(route('social-auth.webhooks.apple'), [
            'signedPayload' => 'invalid-payload',
        ])->assertStatus(400);
    }
}

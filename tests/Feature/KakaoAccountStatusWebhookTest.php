<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class KakaoAccountStatusWebhookTest extends TestCase
{
    private const JWKS_URL = 'https://kauth.kakao.com/.well-known/jwks.json';

    private const REST_API_KEY = 'test-kakao-client-id';

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
    private function makeSet(array $key, array $events): string
    {
        Cache::flush();
        Http::fake([
            self::JWKS_URL => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => 'kakao-test-key',
                    'n' => $key['n'],
                    'e' => $key['e'],
                ]],
            ]),
        ]);

        return JWT::encode([
            'iss' => 'https://kauth.kakao.com',
            'aud' => self::REST_API_KEY,
            'jti' => 'webhook-'.uniqid(),
            'iat' => time(),
            'toe' => time(),
            'app_id' => 'test-app-id',
            'events' => $events,
        ], $key['private'], 'RS256', 'kakao-test-key');
    }

    public function test_tokens_revoked_clears_local_tokens(): void
    {
        $user = User::query()->create(['email' => 'user@example.com']);
        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'kakao',
            'provider_id' => 'kakao-user-1',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
        ]);
        $set = $this->makeSet($this->generateKeyPair(), [
            'https://schemas.openid.net/secevent/oauth/event-type/tokens-revoked' => [
                'subject' => [
                    'subject_type' => 'iss-sub',
                    'iss' => 'https://kauth.kakao.com',
                    'sub' => 'kakao-user-1',
                ],
                'reason' => 'user',
            ],
        ]);

        $response = $this->call(
            'POST',
            route('social-auth.webhooks.kakao'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/secevent+jwt'],
            $set,
        );

        $response->assertStatus(202);
        $account->refresh();
        $this->assertNull($account->access_token);
        $this->assertNull($account->refresh_token);
    }

    public function test_account_purged_removes_only_the_social_account(): void
    {
        $user = User::query()->create(['email' => 'user@example.com']);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'kakao',
            'provider_id' => 'kakao-user-2',
        ]);
        $set = $this->makeSet($this->generateKeyPair(), [
            'https://schemas.openid.net/secevent/risc/event-type/account-purged' => [
                'subject' => [
                    'subject_type' => 'iss-sub',
                    'iss' => 'https://kauth.kakao.com',
                    'sub' => 'kakao-user-2',
                ],
            ],
        ]);

        $response = $this->call(
            'POST',
            route('social-auth.webhooks.kakao'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/secevent+jwt'],
            $set,
        );
        $response->assertStatus(202);

        $this->assertDatabaseMissing('social_accounts', [
            'provider' => 'kakao',
            'provider_id' => 'kakao-user-2',
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_invalid_kakao_webhook_is_rejected(): void
    {
        $this->call(
            'POST',
            route('social-auth.webhooks.kakao'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/secevent+jwt'],
            'invalid-set',
        )->assertStatus(400);
    }
}

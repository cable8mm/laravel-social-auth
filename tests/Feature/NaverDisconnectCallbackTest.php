<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;

class NaverDisconnectCallbackTest extends TestCase
{
    private const CLIENT_ID = 'test-naver-client-id';

    private const CLIENT_SECRET = 'test-naver-secret';

    public function test_valid_callback_removes_the_naver_social_account(): void
    {
        $user = User::query()->create(['email' => 'user@example.com']);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => 'naver',
            'provider_id' => 'naver-user-1',
        ]);
        $timestamp = (string) time();
        $encryptedUniqueId = $this->encryptUniqueId('naver-user-1');

        $this->post(route('social-auth.webhooks.naver'), [
            'clientId' => self::CLIENT_ID,
            'encryptUniqueId' => $encryptedUniqueId,
            'timestamp' => $timestamp,
            'signature' => $this->signature($encryptedUniqueId, $timestamp),
        ])->assertNoContent();

        $this->assertDatabaseMissing('social_accounts', [
            'provider' => 'naver',
            'provider_id' => 'naver-user-1',
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $encryptedUniqueId = $this->encryptUniqueId('naver-user-2');

        $this->post(route('social-auth.webhooks.naver'), [
            'clientId' => self::CLIENT_ID,
            'encryptUniqueId' => $encryptedUniqueId,
            'timestamp' => (string) time(),
            'signature' => 'invalid-signature',
        ])->assertStatus(400);
    }

    private function encryptUniqueId(string $uniqueId): string
    {
        $iv = random_bytes(16);
        $key = substr(md5(self::CLIENT_SECRET, true), 0, 16);
        $encrypted = openssl_encrypt($uniqueId, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return rtrim(strtr(base64_encode($iv.$encrypted), '+/', '-_'), '=');
    }

    private function signature(string $encryptedUniqueId, string $timestamp): string
    {
        $key = substr(md5(self::CLIENT_SECRET, true), 0, 16);
        $base = 'clientId='.self::CLIENT_ID
            .'&encryptUniqueId='.$encryptedUniqueId
            .'&timestamp='.$timestamp;

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $base, $key, true)), '+/', '-_'), '=');
    }
}

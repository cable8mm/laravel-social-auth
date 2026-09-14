<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Unit;

use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Cable8mm\LaravelSocialAuth\Verifiers\AppleTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AppleTokenVerifierTest extends TestCase
{
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
            'x' => $this->base64UrlEncode($details['ec']['x']),
            'y' => $this->base64UrlEncode($details['ec']['y']),
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function test_identity_token_is_verified_and_code_is_exchanged(): void
    {
        $key = $this->generateKeyPair();
        $clientId = 'com.example.web';
        $nonce = 'apple-test-nonce';
        $jwks = [
            'keys' => [[
                'kty' => 'EC',
                'use' => 'sig',
                'alg' => 'ES256',
                'kid' => 'apple-test-key',
                'crv' => 'P-256',
                'x' => $key['x'],
                'y' => $key['y'],
            ]],
        ];

        Cache::flush();
        Http::fake([
            'https://appleid.apple.com/auth/keys' => Http::response($jwks),
            'https://appleid.apple.com/auth/token' => Http::response([
                'access_token' => 'apple-access-token',
                'refresh_token' => 'apple-refresh-token',
                'expires_in' => 3600,
            ]),
        ]);

        $token = JWT::encode([
            'iss' => 'https://appleid.apple.com',
            'aud' => $clientId,
            'sub' => 'apple-user-123',
            'email' => 'user@privaterelay.appleid.com',
            'email_verified' => 'true',
            'nonce' => $nonce,
            'exp' => time() + 3600,
            'iat' => time(),
        ], $key['private'], 'ES256', 'apple-test-key');

        $verifier = new AppleTokenVerifier(
            $clientId,
            'team-id',
            'key-id',
            $key['private'],
            'http://localhost/callback',
        );

        $claims = $verifier->verify($token, $nonce);
        $tokens = $verifier->exchangeCode('authorization-code');

        $this->assertSame('apple-user-123', $claims['sub']);
        $this->assertSame('apple-access-token', $tokens['access_token']);
        Http::assertSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/token'
            && $request['code'] === 'authorization-code'
            && is_string($request['client_secret']));
    }
}

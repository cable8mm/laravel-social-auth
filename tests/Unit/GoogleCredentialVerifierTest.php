<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Unit;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Cable8mm\LaravelSocialAuth\Verifiers\GoogleCredentialVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleCredentialVerifierTest extends TestCase
{
    private string $clientId = 'test-google-client-id';

    private function generateKeyPair(): array
    {
        // Use openssl for key pair
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);

        return [
            'private' => $privateKey,
            'public' => $details['key'],
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function mockJwks(array $key, string $kid = 'test-kid'): void
    {
        $jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => $kid,
                    'n' => $key['n'],
                    'e' => $key['e'],
                ],
            ],
        ];

        Cache::flush();
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($jwks, 200),
        ]);
    }

    private function makeToken(array $key, array $claims, string $kid = 'test-kid'): string
    {
        return JWT::encode($claims, $key['private'], 'RS256', $kid);
    }

    public function test_valid_credential_succeeds(): void
    {
        $key = $this->generateKeyPair();
        $this->mockJwks($key);

        $nonce = 'test-nonce-value-12345';
        $claims = [
            'iss' => 'https://accounts.google.com',
            'aud' => $this->clientId,
            'sub' => 'google-user-123',
            'email' => 'user@gmail.com',
            'email_verified' => true,
            'name' => 'Test User',
            'picture' => 'https://example.com/avatar.jpg',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => $nonce,
        ];

        $token = $this->makeToken($key, $claims);
        $verifier = new GoogleCredentialVerifier($this->clientId);
        $result = $verifier->verify($token, $nonce);

        $this->assertSame('google-user-123', $result['sub']);
        $this->assertSame('user@gmail.com', $result['email']);
        $this->assertTrue($result['email_verified']);
    }

    public function test_invalid_issuer_fails(): void
    {
        $key = $this->generateKeyPair();
        $this->mockJwks($key);

        $claims = [
            'iss' => 'https://evil.example.com',
            'aud' => $this->clientId,
            'sub' => 'google-user-123',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $token = $this->makeToken($key, $claims);
        $verifier = new GoogleCredentialVerifier($this->clientId);

        $this->expectException(SocialAuthException::class);
        $verifier->verify($token);
    }

    public function test_invalid_audience_fails(): void
    {
        $key = $this->generateKeyPair();
        $this->mockJwks($key);

        $claims = [
            'iss' => 'https://accounts.google.com',
            'aud' => 'wrong-client-id',
            'sub' => 'google-user-123',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $token = $this->makeToken($key, $claims);
        $verifier = new GoogleCredentialVerifier($this->clientId);

        $this->expectException(SocialAuthException::class);
        $verifier->verify($token);
    }

    public function test_expired_token_fails(): void
    {
        $key = $this->generateKeyPair();
        $this->mockJwks($key);

        $claims = [
            'iss' => 'https://accounts.google.com',
            'aud' => $this->clientId,
            'sub' => 'google-user-123',
            'exp' => time() - 100,
            'iat' => time() - 3600,
        ];

        $token = $this->makeToken($key, $claims);
        $verifier = new GoogleCredentialVerifier($this->clientId);

        $this->expectException(SocialAuthException::class);
        $verifier->verify($token);
    }

    public function test_nonce_mismatch_fails(): void
    {
        $key = $this->generateKeyPair();
        $this->mockJwks($key);

        $claims = [
            'iss' => 'https://accounts.google.com',
            'aud' => $this->clientId,
            'sub' => 'google-user-123',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => 'actual-nonce',
        ];

        $token = $this->makeToken($key, $claims);
        $verifier = new GoogleCredentialVerifier($this->clientId);

        $this->expectException(SocialAuthException::class);
        $verifier->verify($token, 'expected-different-nonce');
    }

    public function test_missing_subject_fails(): void
    {
        $key = $this->generateKeyPair();
        $this->mockJwks($key);

        $claims = [
            'iss' => 'https://accounts.google.com',
            'aud' => $this->clientId,
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $token = $this->makeToken($key, $claims);
        $verifier = new GoogleCredentialVerifier($this->clientId);

        $this->expectException(SocialAuthException::class);
        $verifier->verify($token);
    }
}

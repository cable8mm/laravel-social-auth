<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Unit;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Cable8mm\LaravelSocialAuth\Verifiers\KakaoTokenVerifier;
use Cable8mm\LaravelSocialAuth\Verifiers\NaverTokenVerifier;
use Illuminate\Support\Facades\Http;

class KakaoNaverVerifierTest extends TestCase
{
    public function test_kakao_code_exchange_success(): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response([
                'access_token' => 'kakao-access-token',
                'refresh_token' => 'kakao-refresh',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ], 200),
            'https://kapi.kakao.com/v2/user/me' => Http::response([
                'id' => 1234567890,
                'properties' => [
                    'nickname' => '카카오닉',
                    'profile_image' => 'https://example.com/kakao.jpg',
                ],
                'kakao_account' => [
                    'email' => 'user@kakao.com',
                    'is_email_verified' => true,
                    'is_email_valid' => true,
                    'profile' => [
                        'nickname' => '카카오닉',
                    ],
                ],
            ], 200),
        ]);

        $verifier = new KakaoTokenVerifier('client-id', 'secret', 'https://app.test/callback');
        $result = $verifier->exchangeCode('auth-code-xyz');

        $this->assertSame('kakao-access-token', $result['tokens']['access_token']);
        $this->assertSame(1234567890, $result['profile']['id']);
        $this->assertSame('user@kakao.com', $result['profile']['kakao_account']['email']);
        $this->assertTrue($result['profile']['kakao_account']['is_email_verified']);
    }

    public function test_kakao_email_missing(): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response([
                'access_token' => 'tok',
                'expires_in' => 3600,
            ], 200),
            'https://kapi.kakao.com/v2/user/me' => Http::response([
                'id' => 111,
                'properties' => ['nickname' => 'nick'],
                'kakao_account' => [
                    // no email
                ],
            ], 200),
        ]);

        $verifier = new KakaoTokenVerifier('id', null, null);
        $result = $verifier->exchangeCode('code');
        $this->assertArrayNotHasKey('email', $result['profile']['kakao_account'] ?? []);
    }

    public function test_kakao_unverified_email_flag(): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response([
                'access_token' => 'tok',
                'expires_in' => 3600,
            ], 200),
            'https://kapi.kakao.com/v2/user/me' => Http::response([
                'id' => 222,
                'kakao_account' => [
                    'email' => 'noverify@kakao.com',
                    'is_email_verified' => false,
                ],
            ], 200),
        ]);

        $verifier = new KakaoTokenVerifier('id', null, null);
        $result = $verifier->exchangeCode('code');
        $this->assertFalse($result['profile']['kakao_account']['is_email_verified']);
    }

    public function test_naver_profile_success(): void
    {
        Http::fake([
            'https://openapi.naver.com/v1/nid/me' => Http::response([
                'resultcode' => '00',
                'message' => 'success',
                'response' => [
                    'id' => 'naver-id-abc',
                    'email' => 'user@naver.com',
                    'name' => '홍길동',
                    'nickname' => '길동이',
                    'profile_image' => 'https://example.com/naver.jpg',
                ],
            ], 200),
        ]);

        $verifier = new NaverTokenVerifier;
        $profile = $verifier->fetchProfile('naver-access-token');

        $this->assertSame('naver-id-abc', $profile['id']);
        $this->assertSame('user@naver.com', $profile['email']);
        $this->assertSame('홍길동', $profile['name']);
    }

    public function test_naver_no_email(): void
    {
        Http::fake([
            'https://openapi.naver.com/v1/nid/me' => Http::response([
                'resultcode' => '00',
                'message' => 'success',
                'response' => [
                    'id' => 'naver-no-email',
                    'name' => '이름만',
                ],
            ], 200),
        ]);

        $verifier = new NaverTokenVerifier;
        $profile = $verifier->fetchProfile('token');
        $this->assertArrayNotHasKey('email', $profile);
    }

    public function test_naver_invalid_token_fails(): void
    {
        Http::fake([
            'https://openapi.naver.com/v1/nid/me' => Http::response([
                'resultcode' => '024',
                'message' => 'Authentication failed',
            ], 401),
        ]);

        $verifier = new NaverTokenVerifier;
        $this->expectException(SocialAuthException::class);
        $verifier->fetchProfile('bad-token');
    }
}

<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Unit;

use Cable8mm\LaravelSocialAuth\Data\ProviderError;
use Cable8mm\LaravelSocialAuth\Events\SocialLoginFailed;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Cable8mm\LaravelSocialAuth\Verifiers\AppleTokenVerifier;
use Cable8mm\LaravelSocialAuth\Verifiers\KakaoTokenVerifier;
use Cable8mm\LaravelSocialAuth\Verifiers\NaverTokenVerifier;
use Closure;
use Illuminate\Support\Facades\Http;

/**
 * Error bodies below are live responses recorded on 2026-10-15 with bogus
 * codes/tokens; the bogus values are replaced with the test values used here.
 */
class ProviderErrorTest extends TestCase
{
    public function test_kakao_token_exchange_error_is_kept_with_the_code_redacted(): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'authorization code not found for code=kakao-secret-code',
                'error_code' => 'KOE320',
            ], 400),
        ]);

        $verifier = new KakaoTokenVerifier('client-id', 'kakao-client-secret', 'https://app.test/callback');
        $e = $this->failure(fn () => $verifier->exchangeCode('kakao-secret-code'));

        $this->assertSame('Verification failed for provider [Kakao]', $e->getMessage());
        $this->assertSame('Token exchange failed', $e->reason());
        $this->assertSame(SocialLoginFailed::INVALID_CREDENTIAL, $e->failureReason());
        $this->assertSame([
            'status' => 400,
            'error' => 'invalid_grant',
            'error_code' => 'KOE320',
            'error_description' => 'authorization code not found for code=[redacted]',
        ], $e->providerError()?->toArray());
        $this->assertSecretsAbsent($e, ['kakao-secret-code', 'kakao-client-secret']);
    }

    public function test_kakao_token_exchange_server_error_keeps_status(): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response(null, 503),
        ]);

        $verifier = new KakaoTokenVerifier('client-id', 'kakao-client-secret', null);
        $e = $this->failure(fn () => $verifier->exchangeCode('kakao-secret-code'));

        $this->assertSame(SocialLoginFailed::PROVIDER_ERROR, $e->failureReason());
        $this->assertSame(503, $e->providerError()?->status);
        $this->assertNull($e->providerError()?->error);
    }

    public function test_kakao_profile_error_is_kept(): void
    {
        Http::fake([
            'https://kapi.kakao.com/v2/user/me' => Http::response([
                'msg' => 'this access token does not exist',
                'reason' => 'UNKNOWN',
                'code' => -401,
            ], 401),
        ]);

        $verifier = new KakaoTokenVerifier('client-id', null, null);
        $e = $this->failure(fn () => $verifier->fetchProfile('kakao-access-token'));

        $this->assertSame('Verification failed for provider [Kakao]', $e->getMessage());
        $this->assertSame('Failed to fetch user profile', $e->reason());
        $this->assertSame([
            'status' => 401,
            'error' => null,
            'error_code' => '-401',
            'error_description' => 'this access token does not exist',
        ], $e->providerError()?->toArray());
    }

    public function test_naver_profile_error_is_kept(): void
    {
        Http::fake([
            'https://openapi.naver.com/v1/nid/me' => Http::response([
                'resultcode' => '024',
                'message' => 'Authentication failed (인증 실패하였습니다.)',
            ], 401),
        ]);

        $e = $this->failure(fn () => (new NaverTokenVerifier)->fetchProfile('naver-access-token'));

        $this->assertSame('Verification failed for provider [Naver]', $e->getMessage());
        $this->assertSame('Failed to fetch user profile', $e->reason());
        $this->assertSame([
            'status' => 401,
            'error' => null,
            'error_code' => '024',
            'error_description' => 'Authentication failed (인증 실패하였습니다.)',
        ], $e->providerError()?->toArray());
    }

    public function test_naver_agreement_error_is_kept(): void
    {
        Http::fake([
            'https://openapi.naver.com/v1/nid/agreement' => Http::response([
                'timestamp' => 1_792_033_342_913,
                'status' => 400,
                'error' => 'Bad Request',
                'detail' => ['reason' => 'InvalidLoginStatus'],
                'path' => '/v1/oauth/agreement',
            ], 400),
        ]);

        $e = $this->failure(fn () => (new NaverTokenVerifier)->fetchAgreement('naver-access-token'));

        $this->assertSame('Verification failed for provider [Naver]', $e->getMessage());
        $this->assertSame('Failed to fetch service agreements', $e->reason());
        $this->assertSame([
            'status' => 400,
            'error' => 'Bad Request',
            'error_code' => 'InvalidLoginStatus',
            'error_description' => null,
        ], $e->providerError()?->toArray());
    }

    public function test_apple_code_exchange_error_is_kept_without_secrets(): void
    {
        Http::fake([
            'https://appleid.apple.com/auth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'The code has expired or has been revoked.',
            ], 400),
        ]);

        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $privateKey);

        $verifier = new AppleTokenVerifier('com.example.web', 'team-id', 'key-id', $privateKey, null);
        $e = $this->failure(fn () => $verifier->exchangeCode('apple-secret-code'));

        $this->assertSame('Verification failed for provider [Apple]', $e->getMessage());
        $this->assertSame('Authorization code exchange failed', $e->reason());
        $this->assertSame([
            'status' => 400,
            'error' => 'invalid_grant',
            'error_code' => null,
            'error_description' => 'The code has expired or has been revoked.',
        ], $e->providerError()?->toArray());
        $this->assertSecretsAbsent($e, ['apple-secret-code']);
    }

    public function test_secrets_sent_with_the_request_are_redacted(): void
    {
        Http::fake([
            'https://example.test/*' => Http::response([
                'error' => 'invalid_token',
                'error_description' => 'token my-access-token rejected; client_secret=abc123&access_token=xyz',
            ], 401),
        ]);

        $error = ProviderError::fromResponse(Http::get('https://example.test/fail'), ['my-access-token']);

        $this->assertSame(
            'token [redacted] rejected; client_secret=[redacted]&access_token=[redacted]',
            $error->errorDescription,
        );
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    private function failure(Closure $callback): SocialAuthException
    {
        try {
            $callback();
        } catch (SocialAuthException $e) {
            return $e;
        }

        $this->fail('Expected SocialAuthException.');
    }

    /**
     * @param  list<string>  $secrets
     */
    private function assertSecretsAbsent(SocialAuthException $e, array $secrets): void
    {
        $dump = $e->getMessage().json_encode($e->providerError()?->toArray());

        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
    }
}

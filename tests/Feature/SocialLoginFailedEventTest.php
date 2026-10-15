<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Events\SocialLoginFailed;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class SocialLoginFailedEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([SocialLoginFailed::class]);
    }

    public function test_existing_email_dispatches_existing_email_unlinked_on_redirect(): void
    {
        app()->setLocale('ko');
        User::create(['name' => 'Existing', 'email' => 'same@example.com', 'password' => bcrypt('secret')]);
        $this->fakeKakao(email: 'same@example.com');

        $response = $this->post(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $this->state(),
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error', '이미 가입된 이메일 주소입니다. 기존 계정으로 로그인한 후 프로필에서 이 SNS 계정을 연결해 주세요.');
        $this->assertFailedOnce('kakao', SocialLoginFailed::EXISTING_EMAIL_UNLINKED);
    }

    public function test_existing_email_dispatches_existing_email_unlinked_on_json(): void
    {
        app()->setLocale('ko');
        User::create(['name' => 'Existing', 'email' => 'same@example.com', 'password' => bcrypt('secret')]);
        $this->fakeKakao(email: 'same@example.com');

        $response = $this->postJson(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $this->state(),
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => '이미 가입된 이메일 주소입니다. 기존 계정으로 로그인한 후 프로필에서 이 SNS 계정을 연결해 주세요.']);
        $this->assertFailedOnce('kakao', SocialLoginFailed::EXISTING_EMAIL_UNLINKED);
    }

    public function test_invalid_state_dispatches_invalid_credential(): void
    {
        Session::put(config('social-auth.session.state'), 'expected-state');

        $response = $this->postJson(route('social-auth.callback', 'kakao'), [
            'code' => 'some-code',
            'state' => 'wrong-state',
        ]);

        $response->assertStatus(422);
        $this->assertFailedOnce('kakao', SocialLoginFailed::INVALID_CREDENTIAL);
    }

    public function test_rejected_authorization_code_dispatches_invalid_credential_on_redirect(): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response(null, 400),
        ]);

        $response = $this->post(route('social-auth.callback', 'kakao'), [
            'code' => 'expired-code',
            'state' => $this->state(),
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Verification failed for provider [Kakao]');
        $this->assertFailedOnce('kakao', SocialLoginFailed::INVALID_CREDENTIAL);
    }

    public function test_event_exception_carries_redacted_provider_error(): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'authorization code not found for code=used-code',
                'error_code' => 'KOE320',
            ], 400),
        ]);

        $response = $this->post(route('social-auth.callback', 'kakao'), [
            'code' => 'used-code',
            'state' => $this->state(),
        ]);

        $response->assertSessionHas('error', 'Verification failed for provider [Kakao]');
        Event::assertDispatched(
            SocialLoginFailed::class,
            fn (SocialLoginFailed $event): bool => $event->exception instanceof SocialAuthException
                && $event->exception->reason() === 'Token exchange failed'
                && $event->exception->providerError()?->errorCode === 'KOE320'
                && $event->exception->providerError()?->errorDescription === 'authorization code not found for code=[redacted]',
        );
    }

    public function test_provider_server_error_dispatches_provider_error(): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response(null, 503),
        ]);

        $response = $this->postJson(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $this->state(),
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Verification failed for provider [Kakao]']);
        $this->assertFailedOnce('kakao', SocialLoginFailed::PROVIDER_ERROR);
    }

    public function test_disabled_provider_dispatches_provider_error(): void
    {
        config(['social-auth.providers.kakao.enabled' => false]);

        $response = $this->post(route('social-auth.callback', 'kakao'), ['code' => 'kakao-code']);

        $response->assertRedirect('/login');
        $this->assertFailedOnce('kakao', SocialLoginFailed::PROVIDER_ERROR);
    }

    public function test_missing_provider_consent_dispatches_consent_rejected(): void
    {
        app()->setLocale('ko');
        config([
            'social-auth.consent.providers.naver.enabled' => true,
            'social-auth.consent.providers.naver.term_codes' => [
                'terms_of_service' => 'terms-code',
                'privacy_policy' => 'privacy-code',
            ],
        ]);

        Http::fake([
            'https://openapi.naver.com/v1/nid/me' => Http::response([
                'resultcode' => '00',
                'response' => [
                    'id' => 'naver-missing-consent-user',
                    'email' => 'naver-missing@example.com',
                ],
            ]),
            'https://openapi.naver.com/v1/nid/agreement' => Http::response([
                'result' => 'success',
                'agreementInfos' => [
                    [
                        'termCode' => 'terms-code',
                        'clientId' => 'test-naver-client-id',
                        'agreeDate' => '04:25:06.123 PM 09/16/2026',
                    ],
                ],
            ]),
        ]);

        $response = $this->post(route('social-auth.callback', 'naver'), [
            'access_token' => 'naver-access-token',
            'state' => $this->state(),
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error', '네이버에서 필수 약관 동의를 확인하지 못했습니다. 다시 시도해 주세요.');
        $this->assertGuest();
        $this->assertFailedOnce('naver', SocialLoginFailed::CONSENT_REJECTED);
    }

    public function test_successful_login_does_not_dispatch(): void
    {
        $user = User::create(['name' => 'Existing', 'email' => 'linked@example.com', 'password' => null]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'kakao',
            'provider_id' => '12345',
            'email' => 'linked@example.com',
        ]);
        $this->fakeKakao(email: 'linked@example.com');

        $response = $this->post(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $this->state(),
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        Event::assertNotDispatched(SocialLoginFailed::class);
    }

    public function test_new_user_sent_to_consent_does_not_dispatch(): void
    {
        $this->fakeKakao(email: 'new@example.com');

        $response = $this->postJson(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $this->state(),
        ]);

        $response->assertOk();
        Event::assertNotDispatched(SocialLoginFailed::class);
    }

    public function test_connect_flow_failure_does_not_dispatch(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);
        SocialAccount::create([
            'user_id' => $owner->id,
            'provider' => 'kakao',
            'provider_id' => '12345',
        ]);
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('x')]);
        $this->fakeKakao(email: 'kakao@example.com');

        $this->actingAs($user);
        $state = $this->getJson(route('social-auth.state', [
            'context' => 'connect',
            'provider' => 'kakao',
        ]))->json('state');

        $response = $this->postJson(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $state,
        ]);

        $response->assertStatus(422);
        Event::assertNotDispatched(SocialLoginFailed::class);
    }

    private function state(): string
    {
        return $this->getJson(route('social-auth.state'))->json('state');
    }

    private function fakeKakao(string $email): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response([
                'access_token' => 'kakao-access-token',
                'refresh_token' => 'kakao-refresh-token',
                'expires_in' => 21_600,
                'token_type' => 'bearer',
            ]),
            'https://kapi.kakao.com/v2/user/me' => Http::response([
                'id' => 12_345,
                'properties' => ['nickname' => 'Kakao User'],
                'kakao_account' => [
                    'email' => $email,
                    'is_email_verified' => true,
                    'is_email_valid' => true,
                ],
            ]),
        ]);
    }

    private function assertFailedOnce(string $provider, string $reason): void
    {
        Event::assertDispatchedTimes(SocialLoginFailed::class, 1);
        Event::assertDispatched(
            SocialLoginFailed::class,
            fn (SocialLoginFailed $event): bool => $event->provider === $provider
                && $event->reason === $reason
                && $event->exception instanceof SocialAuthException,
        );
    }
}

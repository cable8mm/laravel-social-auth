<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Support\Facades\Session;

class RoutesAndUiTest extends TestCase
{
    public function test_nonce_endpoint(): void
    {
        $response = $this->getJson(route('social-auth.nonce'));
        $response->assertOk();
        $response->assertJsonStructure(['nonce']);
        $this->assertNotEmpty($response->json('nonce'));
    }

    public function test_state_endpoint(): void
    {
        $response = $this->getJson(route('social-auth.state'));
        $response->assertOk();
        $response->assertJsonStructure(['state']);
    }

    public function test_nonce_endpoint_remembers_the_login_origin(): void
    {
        $response = $this->getJson(route('social-auth.nonce', [
            'redirect' => '/settings?tab=security',
        ]));

        $response->assertOk();
        $this->assertSame(
            '/settings?tab=security',
            session(config('social-auth.session.intended')),
        );
    }

    public function test_nonce_endpoint_rejects_external_login_origin(): void
    {
        $this->getJson(route('social-auth.nonce', [
            'redirect' => 'https://attacker.test/account',
        ]))->assertOk();

        $this->assertNull(session(config('social-auth.session.intended')));
    }

    public function test_consent_page_requires_pending(): void
    {
        $response = $this->get(route('social-auth.consent'));
        $response->assertRedirect();
    }

    public function test_consent_page_with_pending(): void
    {
        Session::put(config('social-auth.session.pending_registration'), [
            'provider' => 'kakao',
            'provider_id' => 'x',
            'email' => null,
            'email_verified' => false,
        ]);

        $response = $this->get(route('social-auth.consent'));
        $response->assertOk();
        $response->assertSee('약관 동의');
        $response->assertSee('social-auth-consent');
        $response->assertSee('social-auth-consent__submit');
        $response->assertSee('social-auth-term-terms_of_service');
        $response->assertSee('<!DOCTYPE html>', false);
        $response->assertSee('Laravel Social Auth', false);
    }

    public function test_callback_invalid_state(): void
    {
        Session::put(config('social-auth.session.state'), 'expected-state');

        $response = $this->postJson(route('social-auth.callback', 'kakao'), [
            'code' => 'some-code',
            'state' => 'wrong-state',
        ]);

        $response->assertStatus(422);
    }

    public function test_callback_accepts_kakao_get_redirect(): void
    {
        Session::put(config('social-auth.session.state'), 'expected-state');

        $response = $this->getJson(route('social-auth.callback', 'kakao', [
            'code' => 'some-code',
            'state' => 'wrong-state',
        ]));

        $response->assertStatus(422);
    }

    public function test_naver_callback_renders_fragment_handoff_page(): void
    {
        $response = $this->get(route('social-auth.callback', 'naver'));

        $response->assertOk();
        $response->assertSee('네이버 로그인 처리 중입니다.');
        $response->assertSee('access_token');
        $response->assertSee("form.method = 'POST'", false);
        $response->assertSee('form.submit()');
        $response->assertSee('window.history.replaceState');
    }

    public function test_callback_without_provider_payload_is_rejected_as_cancelled(): void
    {
        foreach (['google', 'kakao', 'naver'] as $provider) {
            $response = $this->postJson(route('social-auth.callback', $provider), []);

            $response->assertUnprocessable();
        }
    }

    public function test_disconnect_requires_auth(): void
    {
        $response = $this->deleteJson(route('social-auth.disconnect', 'google'));
        // Unauthenticated middleware redirects or 401
        $this->assertTrue(in_array($response->status(), [401, 302, 403]));
    }

    public function test_connect_requires_auth(): void
    {
        $response = $this->postJson(route('social-auth.connect', 'google'), []);
        $this->assertTrue(in_array($response->status(), [401, 302, 403]));
    }

    public function test_buttons_component_hides_disabled(): void
    {
        config(['social-auth.providers.google.enabled' => false]);

        $html = view('social-auth::components.buttons', ['context' => 'login'])->render();
        $this->assertStringNotContainsString('data-provider="google"', $html);
        $this->assertStringContainsString('data-provider="naver"', $html);
        $this->assertStringContainsString('data-provider="kakao"', $html);
    }

    public function test_connected_accounts_puts_disconnect_action_on_the_right(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret'),
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'naver',
            'provider_id' => 'naver-user',
        ]);

        $this->actingAs($user);
        $html = view('social-auth::components.connected-accounts')->render();

        $this->assertStringContainsString('class="shrink-0"', $html);
        $this->assertStringContainsString('cursor-pointer', $html);
        $this->assertStringContainsString('연결 해제', $html);
    }

    public function test_apple_button_uses_the_apple_js_sdk(): void
    {
        config([
            'social-auth.providers.apple.enabled' => true,
            'social-auth.providers.apple.client_id' => 'com.example.web',
            'social-auth.providers.apple.js_sdk_url' => 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid.auth.js',
        ]);

        $html = view('social-auth::components.button', [
            'provider' => 'apple',
            'context' => 'login',
        ])->render();

        $this->assertStringContainsString('data-provider="apple"', $html);
        $this->assertStringContainsString('id="appleid-signin"', $html);
        $this->assertStringContainsString('data-color="black"', $html);
        $this->assertStringContainsString('data-type="sign-in"', $html);
        $this->assertStringContainsString('appleid.cdn-apple.com/appleauth', $html);
    }

    public function test_kakao_button_does_not_use_an_invalid_integrity_hash(): void
    {
        $html = view('social-auth::components.button', [
            'provider' => 'kakao',
            'context' => 'login',
        ])->render();

        $this->assertStringContainsString('src="https://t1.kakaocdn.net/kakao_js_sdk/', $html);
        $this->assertStringNotContainsString('integrity=', $html);
    }

    public function test_naver_button_uses_the_package_button_markup(): void
    {
        $html = view('social-auth::components.button', [
            'provider' => 'naver',
            'context' => 'login',
        ])->render();

        $this->assertStringContainsString('class="btn-naver ', $html);
        $this->assertStringContainsString('class="naver-symbol ', $html);
        $this->assertStringContainsString('<path d="M4 4h5.5l5 7.1V4H20v16h-5.5l-5-7.1V20H4V4Z"', $html);
        $this->assertStringContainsString('네이버 아이디로 로그인', $html);
        $this->assertStringNotContainsString('naverIdLogin_loginButton', $html);
    }

    public function test_register_buttons_use_registration_labels(): void
    {
        config([
            'social-auth.providers.apple.enabled' => true,
            'social-auth.providers.apple.client_id' => 'com.example.web',
            'social-auth.providers.apple.js_sdk_url' => 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid.auth.js',
        ]);

        $naver = view('social-auth::components.button', [
            'provider' => 'naver',
            'context' => 'register',
        ])->render();
        $kakao = view('social-auth::components.button', [
            'provider' => 'kakao',
            'context' => 'register',
        ])->render();
        $apple = view('social-auth::components.button', [
            'provider' => 'apple',
            'context' => 'register',
        ])->render();

        $this->assertStringContainsString('네이버로 시작하기', $naver);
        $this->assertStringContainsString('카카오로 시작하기', $kakao);
        $this->assertStringContainsString('data-type="sign-up"', $apple);
    }

    public function test_kakao_button_includes_tailwind_styling(): void
    {
        $html = view('social-auth::components.button', [
            'provider' => 'kakao',
            'context' => 'login',
        ])->render();

        $this->assertStringContainsString('class="btn-kakao ', $html);
        $this->assertStringContainsString('bg-[#FEE500]', $html);
        $this->assertStringContainsString('rounded-xl', $html);
        $this->assertStringContainsString('카카오 로그인', $html);
        $this->assertStringContainsString('class="kakao-symbol ', $html);
    }

    public function test_social_buttons_share_default_dimensions(): void
    {
        $html = view('social-auth::components.buttons', ['context' => 'login'])->render();

        $this->assertStringContainsString('max-w-[17.5rem]', $html);
        $this->assertStringContainsString('mx-auto', $html);
        $this->assertStringContainsString('min-h-12', $html);
        $this->assertStringContainsString('bg-[#03A94D]', $html);
        $this->assertStringContainsString('class="btn-naver ', $html);
        $this->assertStringContainsString('w-full', $html);
    }

    public function test_google_one_tap_is_opt_in_for_guests(): void
    {
        config(['social-auth.providers.google.one_tap' => true]);

        $html = view('social-auth::components.one-tap')->render();

        $this->assertStringContainsString('data-google-one-tap', $html);
        $this->assertStringContainsString('https://accounts.google.com/gsi/client', $html);
        $this->assertStringNotContainsString('social-auth::scripts', $html);
    }

    public function test_google_one_tap_is_hidden_when_disabled_or_authenticated(): void
    {
        $this->assertStringNotContainsString(
            'data-google-one-tap',
            view('social-auth::components.one-tap')->render()
        );

        config(['social-auth.providers.google.one_tap' => true]);
        $this->actingAs(new User([
            'id' => 1,
            'email' => 'signed-in@example.com',
        ]));

        $this->assertStringNotContainsString(
            'data-google-one-tap',
            view('social-auth::components.one-tap')->render()
        );
    }
}

<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

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

    public function test_kakao_button_does_not_use_an_invalid_integrity_hash(): void
    {
        $html = view('social-auth::components.button', [
            'provider' => 'kakao',
            'context' => 'login',
        ])->render();

        $this->assertStringContainsString('src="https://t1.kakaocdn.net/kakao_js_sdk/', $html);
        $this->assertStringNotContainsString('integrity=', $html);
    }

    public function test_naver_button_uses_the_sdk_mount_point(): void
    {
        $html = view('social-auth::components.button', [
            'provider' => 'naver',
            'context' => 'login',
        ])->render();

        $this->assertStringContainsString('id="naverIdLogin"', $html);
    }

    public function test_kakao_button_includes_package_default_styling(): void
    {
        $html = view('social-auth::components.button', [
            'provider' => 'kakao',
            'context' => 'login',
        ])->render();

        $this->assertStringContainsString('class="btn-kakao"', $html);
        $this->assertStringContainsString('background: #fee500', $html);
        $this->assertStringContainsString('border-radius: 0.75rem', $html);
    }

    public function test_social_buttons_share_default_dimensions(): void
    {
        $html = view('social-auth::components.buttons', ['context' => 'login'])->render();

        $this->assertStringContainsString('max-width: 17.5rem', $html);
        $this->assertStringContainsString('margin-inline: auto', $html);
        $this->assertStringContainsString('height: 3rem !important', $html);
        $this->assertStringContainsString('background: #00c73c', $html);
        $this->assertStringContainsString('object-fit: contain', $html);
        $this->assertStringContainsString('width: auto !important', $html);
    }
}

<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Browser;

use Laravel\Dusk\Browser;

class SocialAuthBrowserTest extends DuskTestCase
{
    public function test_social_buttons_are_rendered_in_configured_order(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertPresent('[data-provider="naver"]')
                ->assertPresent('[data-provider="kakao"]')
                ->assertPresent('[data-provider="google"]')
                ->assertScript(
                    'return [...document.querySelectorAll("[data-provider]")].map((element) => element.dataset.provider)',
                    ['naver', 'kakao', 'google']
                );
        });
    }

    public function test_workbench_has_login_register_and_profile_screens(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/login')
                ->assertPathIs('/login')
                ->assertSee('로그인')
                ->assertPresent('form.workbench-form')
                ->clickLink('회원가입')
                ->assertPathIs('/register')
                ->assertSee('회원가입')
                ->assertPresent('form.workbench-form')
                ->visit('/profile')
                ->assertPathIs('/login');
        });
    }

    public function test_pending_registration_can_complete_consent_in_browser(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/test/social-auth/prepare-consent')
                ->assertPathIs('/social-auth/consent')
                ->assertSee('약관 동의')
                ->check('terms_of_service')
                ->check('privacy_policy')
                ->press('동의하고 가입 완료')
                ->waitForLocation('/')
                ->assertPathIs('/')
                ->assertSee('로그인됨: dusk@example.com');
        });
    }
}

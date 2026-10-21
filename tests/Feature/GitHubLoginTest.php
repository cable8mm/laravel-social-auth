<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Events\SocialAccountDisconnected;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class GitHubLoginTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('social-auth.providers.github.enabled', true);
        $app['config']->set('social-auth.providers.github.client_id', 'test-github-client-id');
        $app['config']->set('social-auth.providers.github.client_secret', 'test-github-secret');
        $app['config']->set('social-auth.providers.github.redirect', 'http://localhost/social-auth/github/callback');
    }

    private function fakeGitHub(array $emails): void
    {
        Http::fake([
            // GitHub App user-token response (documented: expires_in 28800, refresh_token_expires_in 15897600).
            'https://github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'ghu_test',
                'expires_in' => 28800,
                'refresh_token' => 'ghr_test',
                'refresh_token_expires_in' => 15897600,
                'scope' => '',
                'token_type' => 'bearer',
            ]),
            'https://api.github.com/user' => Http::response([
                'login' => 'octocat',
                'id' => 583231,
                'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=4',
                'name' => 'The Octocat',
                'email' => null,
            ]),
            'https://api.github.com/user/emails' => Http::response($emails),
        ]);
    }

    private function githubAccount(?\DateTimeInterface $expiresAt): User
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '583231',
            'access_token' => 'ghu_stored',
            'refresh_token' => 'ghr_stored',
            'token_expires_at' => $expiresAt,
        ]);

        return $user;
    }

    public function test_get_callback_stores_pending_registration_with_verified_email(): void
    {
        $this->fakeGitHub([
            ['email' => 'octocat@github.com', 'verified' => true, 'primary' => true, 'visibility' => 'public'],
        ]);

        $state = $this->getJson(route('social-auth.state'))->json('state');

        $this->get(route('social-auth.callback', ['provider' => 'github', 'code' => 'github-code', 'state' => $state]))
            ->assertRedirect(config('social-auth.redirects.registration_consent'));

        $pending = Session::get(config('social-auth.session.pending_registration'));
        $this->assertSame('github', $pending['provider']);
        $this->assertSame('583231', $pending['provider_id']);
        $this->assertSame('octocat@github.com', $pending['email']);
        $this->assertSame('ghr_test', $pending['refresh_token']);
        $this->assertNotNull($pending['token_expires_at']);
        $this->assertTrue($pending['email_verified']);
        $this->assertSame('The Octocat', $pending['name']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_pending_registration_keeps_only_whitelisted_raw_fields(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_test', 'token_type' => 'bearer']),
            'https://api.github.com/user' => Http::response([
                'login' => 'octocat',
                'id' => 583231,
                'node_id' => 'MDQ6VXNlcjE=',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=4',
                'html_url' => 'https://github.com/octocat',
                'followers_url' => 'https://api.github.com/users/octocat/followers',
                'repos_url' => 'https://api.github.com/users/octocat/repos',
                'type' => 'User',
                'name' => 'The Octocat',
                'created_at' => '2011-01-25T18:44:36Z',
                'plan' => ['name' => 'free'],
            ]),
            'https://api.github.com/user/emails' => Http::response([]),
        ]);

        $state = $this->getJson(route('social-auth.state'))->json('state');
        $this->get(route('social-auth.callback', ['provider' => 'github', 'code' => 'github-code', 'state' => $state]));

        $raw = Session::get(config('social-auth.session.pending_registration'))['raw'];
        $this->assertSame(
            ['avatar_url', 'created_at', 'html_url', 'id', 'login', 'name', 'type'],
            collect($raw)->keys()->sort()->values()->all(),
        );
    }

    public function test_missing_verified_primary_email_leaves_email_null(): void
    {
        $this->fakeGitHub([
            ['email' => 'octocat@github.com', 'verified' => false, 'primary' => true, 'visibility' => 'public'],
        ]);

        $state = $this->getJson(route('social-auth.state'))->json('state');

        $this->get(route('social-auth.callback', ['provider' => 'github', 'code' => 'github-code', 'state' => $state]))
            ->assertRedirect(config('social-auth.redirects.registration_consent'));

        $pending = Session::get(config('social-auth.session.pending_registration'));
        $this->assertNull($pending['email']);
        $this->assertFalse($pending['email_verified']);
    }

    public function test_invalid_state_is_rejected_before_token_exchange(): void
    {
        Http::fake();
        $this->getJson(route('social-auth.state'));

        $this->get(route('social-auth.callback', ['provider' => 'github', 'code' => 'github-code', 'state' => 'forged']))
            ->assertRedirect(config('social-auth.redirects.failure'));

        Http::assertNothingSent();
    }

    public function test_existing_github_account_logs_in(): void
    {
        $this->fakeGitHub([]);
        $user = User::create(['name' => 'Existing', 'email' => 'existing@example.com']);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '583231',
        ]);

        $state = $this->getJson(route('social-auth.state'))->json('state');

        $this->get(route('social-auth.callback', ['provider' => 'github', 'code' => 'github-code', 'state' => $state]))
            ->assertRedirect(config('social-auth.redirects.login_success'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_sign_up_redirects_to_the_page_it_started_from_not_the_consent_screen(): void
    {
        $this->fakeGitHub([
            ['email' => 'octocat@github.com', 'verified' => true, 'primary' => true, 'visibility' => 'public'],
        ]);

        $state = $this->getJson(route('social-auth.state', ['redirect' => '/login']))->json('state');

        $this->get(route('social-auth.callback', ['provider' => 'github', 'code' => 'github-code', 'state' => $state]))
            ->assertRedirect(config('social-auth.redirects.registration_consent'));

        $this->get(route('social-auth.consent'))->assertOk();

        // The guest One Tap component in the application layout also renders on
        // the consent screen and fetches a nonce with its own URL as `redirect`.
        $this->getJson(route('social-auth.nonce', ['redirect' => route('social-auth.consent')]))->assertOk();

        $this->post(route('social-auth.consent.submit'), [
            'terms_of_service' => '1',
            'privacy_policy' => '1',
        ])->assertRedirect('/login');

        $this->assertAuthenticated();
    }

    public function test_disconnect_attempts_github_grant_revoke(): void
    {
        Http::fake([
            'https://api.github.com/applications/test-github-client-id/grant' => Http::response(null, 204),
        ]);
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_id' => '583231',
            'access_token' => 'gho_test',
        ]);

        app(SocialLoginManager::class)->disconnect('github', $user);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request['access_token'] === 'gho_test');
        $this->assertDatabaseMissing(config('social-auth.table'), ['provider' => 'github']);
    }

    public function test_disconnect_with_valid_token_revokes_without_refresh(): void
    {
        Http::fake([
            'https://api.github.com/applications/test-github-client-id/grant' => Http::response(null, 204),
        ]);
        $user = $this->githubAccount(now()->addHours(4));

        app(SocialLoginManager::class)->disconnect('github', $user);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request['access_token'] === 'ghu_stored');
    }

    public function test_disconnect_with_expired_token_refreshes_then_revokes_with_new_token(): void
    {
        Event::fake([SocialAccountDisconnected::class]);
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'ghu_refreshed',
                'expires_in' => 28800,
                'refresh_token' => 'ghr_refreshed',
                'refresh_token_expires_in' => 15897600,
                'scope' => '',
                'token_type' => 'bearer',
            ]),
            'https://api.github.com/applications/test-github-client-id/grant' => Http::response(null, 204),
        ]);
        $user = $this->githubAccount(now()->subHour());

        app(SocialLoginManager::class)->disconnect('github', $user);

        Http::assertSent(fn ($request) => $request->url() === 'https://github.com/login/oauth/access_token'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'ghr_stored');
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request['access_token'] === 'ghu_refreshed');
        Event::assertDispatched(SocialAccountDisconnected::class, fn ($event) => $event->remoteRevoked === true);
        $this->assertDatabaseMissing(config('social-auth.table'), ['provider' => 'github']);
    }

    public function test_failed_refresh_skips_revoke_but_still_removes_local_account(): void
    {
        Event::fake([SocialAccountDisconnected::class]);
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response([
                'error' => 'incorrect_client_credentials',
                'error_description' => 'The client_id and/or client_secret passed are incorrect.',
            ]),
        ]);
        $user = $this->githubAccount(now()->subHour());

        app(SocialLoginManager::class)->disconnect('github', $user);

        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
        Event::assertDispatched(SocialAccountDisconnected::class, fn ($event) => $event->remoteRevoked === false);
        $this->assertDatabaseMissing(config('social-auth.table'), ['provider' => 'github']);
    }

    public function test_button_redirects_to_github_authorize_url(): void
    {
        app()->setLocale('ko');

        $html = view('social-auth::components.button', [
            'provider' => 'github',
            'context' => 'login',
        ])->render();

        $this->assertStringContainsString('data-provider="github"', $html);
        $this->assertStringContainsString('data-authorize-url="https://github.com/login/oauth/authorize"', $html);
        $this->assertStringContainsString("window.SocialAuth.loginGitHub('login')", $html);
        $this->assertStringContainsString('GitHub로 로그인', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_default_order_places_github_after_google(): void
    {
        $defaults = require __DIR__.'/../../config/social-auth.php';

        $this->assertSame(['naver', 'kakao', 'google', 'github', 'apple'], $defaults['button_order']);
    }
}

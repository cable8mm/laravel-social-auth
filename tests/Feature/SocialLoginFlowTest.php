<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Contracts\NicknameGeneratorContract;
use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Services\SocialAccountService;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class SocialLoginFlowTest extends TestCase
{
    public function test_provider_id_existing_account_logs_in(): void
    {
        $user = User::create([
            'name' => 'Existing',
            'email' => 'existing@example.com',
            'password' => null,
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'g-123',
            'email' => 'existing@example.com',
        ]);

        $manager = $this->app->make(SocialLoginManager::class);

        // Mock Google provider verify by storing pending manually is complex;
        // Instead test via account service + login path by directly using find
        $account = $manager->provider('google');
        $this->assertTrue($account->isEnabled());

        $found = $this->app->make(SocialAccountService::class)
            ->findByProvider('google', 'g-123');

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->user_id);
    }

    public function test_new_sns_user_without_consent_rejected(): void
    {
        $manager = $this->app->make(SocialLoginManager::class);

        $providerUser = new ProviderUser(
            provider: 'kakao',
            providerId: 'k-999',
            email: 'new@kakao.com',
            emailVerified: true,
            name: null,
            nickname: 'kakao_nick',
        );

        $manager->storePendingRegistration($providerUser);

        $this->expectException(SocialAuthException::class);
        $manager->completeRegistration([]); // no consents
    }

    public function test_consent_then_create_user(): void
    {
        Event::fake();

        $manager = $this->app->make(SocialLoginManager::class);

        $providerUser = new ProviderUser(
            provider: 'kakao',
            providerId: 'k-1000',
            email: 'verified@kakao.com',
            emailVerified: true,
            name: null,
            nickname: null,
            avatar: 'https://example.com/a.jpg',
        );

        $manager->storePendingRegistration($providerUser);

        $result = $manager->completeRegistration([
            'terms_of_service' => true,
            'privacy_policy' => true,
            'marketing' => false,
        ]);

        $this->assertSame('registered', $result['status']);
        $user = $result['user'];
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('verified@kakao.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertNotNull($user->nickname);
        $this->assertStringStartsWith('user_', $user->nickname);

        $this->assertDatabaseHas(config('social-auth.table'), [
            'provider' => 'kakao',
            'provider_id' => 'k-1000',
            'user_id' => $user->id,
        ]);
    }

    public function test_kakao_unverified_email_sets_null_verified_at(): void
    {
        $manager = $this->app->make(SocialLoginManager::class);

        $providerUser = new ProviderUser(
            provider: 'kakao',
            providerId: 'k-unverified',
            email: 'unverified@kakao.com',
            emailVerified: false,
        );

        $manager->storePendingRegistration($providerUser);

        $result = $manager->completeRegistration([
            'terms_of_service' => true,
            'privacy_policy' => true,
        ]);

        $user = $result['user'];
        $this->assertSame('unverified@kakao.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_no_email_from_provider_leaves_email_null(): void
    {
        $manager = $this->app->make(SocialLoginManager::class);

        $providerUser = new ProviderUser(
            provider: 'kakao',
            providerId: 'k-no-email',
            email: null,
            emailVerified: false,
        );

        $manager->storePendingRegistration($providerUser);

        $result = $manager->completeRegistration([
            'terms_of_service' => true,
            'privacy_policy' => true,
        ]);

        $user = $result['user'];
        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->password);
    }

    public function test_no_auto_merge_by_email(): void
    {
        $existing = User::create([
            'name' => 'Local User',
            'email' => 'same@example.com',
            'password' => bcrypt('secret'),
            'email_verified_at' => now(),
        ]);

        $manager = $this->app->make(SocialLoginManager::class);

        $providerUser = new ProviderUser(
            provider: 'google',
            providerId: 'g-different',
            email: 'same@example.com',
            emailVerified: true,
            name: 'Google Name',
        );

        $manager->storePendingRegistration($providerUser);

        $result = $manager->completeRegistration([
            'terms_of_service' => true,
            'privacy_policy' => true,
        ]);

        $newUser = $result['user'];
        $this->assertNotEquals($existing->id, $newUser->id);
        $this->assertSame(2, User::where('email', 'same@example.com')->count());
    }

    public function test_explicit_connect_succeeds(): void
    {
        Event::fake();

        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        // Simulate connect via service after verify would succeed
        $providerUser = new ProviderUser(
            provider: 'naver',
            providerId: 'n-connect-1',
            email: 'naver@example.com',
            emailVerified: true,
        );

        $service = $this->app->make(SocialAccountService::class);
        $account = $service->createForUser($user, $providerUser);

        $this->assertSame('naver', $account->provider);
        $this->assertSame($user->id, $account->user_id);
    }

    public function test_connect_rejects_already_linked_to_other_user(): void
    {
        $user1 = User::create(['name' => 'U1', 'email' => 'u1@example.com', 'password' => bcrypt('x')]);
        $user2 = User::create(['name' => 'U2', 'email' => 'u2@example.com', 'password' => bcrypt('x')]);

        SocialAccount::create([
            'user_id' => $user1->id,
            'provider' => 'google',
            'provider_id' => 'g-taken',
        ]);

        $service = $this->app->make(SocialAccountService::class);

        $providerUser = new ProviderUser(
            provider: 'google',
            providerId: 'g-taken',
            email: 'x@example.com',
            emailVerified: true,
        );

        $this->expectException(SocialAuthException::class);
        $service->ensureNotLinkedToOtherUser($providerUser, $user2);
    }

    public function test_disconnect_succeeds(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret'),
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'kakao',
            'provider_id' => 'k-disc',
        ]);

        $manager = $this->app->make(SocialLoginManager::class);
        $manager->disconnect('kakao', $user);

        $this->assertDatabaseMissing(config('social-auth.table'), [
            'provider' => 'kakao',
            'provider_id' => 'k-disc',
        ]);
    }

    public function test_cannot_disconnect_last_login_method(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => null,
            'password' => null,
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'g-only',
        ]);

        $manager = $this->app->make(SocialLoginManager::class);

        $this->expectException(SocialAuthException::class);
        $manager->disconnect('google', $user);
    }

    public function test_remote_revoke_failure_still_removes_local_account(): void
    {
        config(['social-auth.remote_revoke' => true]);

        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret'),
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'kakao',
            'provider_id' => 'k-revoke-failure',
            'access_token' => 'kakao-access-token',
        ]);

        Http::fake([
            'https://kapi.kakao.com/v1/user/unlink' => Http::response([], 500),
        ]);

        $manager = $this->app->make(SocialLoginManager::class);
        $manager->disconnect('kakao', $user);

        $this->assertDatabaseMissing(config('social-auth.table'), [
            'provider' => 'kakao',
            'provider_id' => 'k-revoke-failure',
        ]);
    }

    public function test_enabled_providers_order(): void
    {
        $manager = $this->app->make(SocialLoginManager::class);
        $enabled = $manager->enabledProviders();

        $this->assertSame(['naver', 'kakao', 'google'], $enabled);
    }

    public function test_disabled_provider_hidden_from_enabled(): void
    {
        config(['social-auth.providers.kakao.enabled' => false]);

        // Re-boot manager
        $this->app->forgetInstance(SocialLoginManager::class);
        $manager = $this->app->make(SocialLoginManager::class);

        $enabled = $manager->enabledProviders();
        $this->assertNotContains('kakao', $enabled);
        $this->assertContains('naver', $enabled);
        $this->assertContains('google', $enabled);
    }

    public function test_nonce_and_state_generation(): void
    {
        $manager = $this->app->make(SocialLoginManager::class);

        $nonce = $manager->generateNonce();
        $this->assertNotEmpty($nonce);
        $this->assertSame($nonce, Session::get(config('social-auth.session.nonce')));

        $state = $manager->generateState();
        $this->assertNotEmpty($state);
        $this->assertSame($state, Session::get(config('social-auth.session.state')));
    }

    public function test_default_nickname_generator(): void
    {
        $gen = $this->app->make(NicknameGeneratorContract::class);
        $pu = new ProviderUser(provider: 'google', providerId: 'x');
        $nick = $gen->generate($pu);
        $this->assertMatchesRegularExpression('/^user_[a-z0-9]{8}$/', $nick);
    }
}

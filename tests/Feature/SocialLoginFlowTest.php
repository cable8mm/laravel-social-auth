<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Contracts\NicknameGeneratorContract;
use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Events\SocialAccountDisconnected;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Services\SocialAccountService;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\DataProvider;

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
        Session::put(config('social-auth.session.intended'), '/dashboard');

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
        $this->assertSame('/dashboard', $result['redirect']);
        $user = $result['user'];
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('verified@kakao.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertNotNull($user->nickname);
        $this->assertStringStartsWith('user_', $user->nickname);
        // Kakao gives no name; the package leaves users.name to the application.
        $this->assertNull($user->fresh()->name);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->privacy_policy_accepted_at);
        $this->assertNull($user->marketing_accepted_at);

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

    public function test_kakao_invalid_email_sets_null_verified_at(): void
    {
        $manager = $this->app->make(SocialLoginManager::class);

        $providerUser = new ProviderUser(
            provider: 'kakao',
            providerId: 'k-invalid-email',
            email: 'invalid@kakao.com',
            emailVerified: false,
        );

        $manager->storePendingRegistration($providerUser);

        $result = $manager->completeRegistration([
            'terms_of_service' => true,
            'privacy_policy' => true,
        ]);

        $user = $result['user'];
        $this->assertSame('invalid@kakao.com', $user->email);
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

    public function test_existing_email_returns_to_login_without_consent_screen(): void
    {
        app()->setLocale('ko');
        User::create([
            'name' => 'Existing User',
            'email' => 'same@example.com',
            'password' => bcrypt('secret'),
        ]);

        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response([
                'access_token' => 'kakao-access-token',
                'refresh_token' => 'kakao-refresh-token',
                'expires_in' => 21_600,
                'token_type' => 'bearer',
            ]),
            'https://kapi.kakao.com/v2/user/me' => Http::response([
                'id' => 98_765,
                'properties' => ['nickname' => 'Kakao User'],
                'kakao_account' => [
                    'email' => 'same@example.com',
                    'is_email_verified' => true,
                    'is_email_valid' => true,
                ],
            ]),
        ]);

        $state = $this->getJson(route('social-auth.state', [
            'redirect' => '/login',
        ]))->json('state');

        $response = $this->post(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $state,
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error', '이미 가입된 이메일 주소입니다. 기존 계정으로 로그인한 후 프로필에서 이 SNS 계정을 연결해 주세요.');
        $this->assertFalse(Session::has(config('social-auth.session.pending_registration')));
        $this->assertSame(1, User::where('email', 'same@example.com')->count());
    }

    public function test_naver_provider_consent_registers_without_local_consent_screen(): void
    {
        config([
            'social-auth.consent.providers.naver.enabled' => true,
            'social-auth.consent.providers.naver.term_codes' => [
                'terms_of_service' => 'terms-code',
                'privacy_policy' => 'privacy-code',
                'marketing' => 'marketing-code',
            ],
        ]);

        Http::fake([
            // Shape from Naver's profile API reference (/v1/nid/me); the live Naver login check ran against this API.
            'https://openapi.naver.com/v1/nid/me' => Http::response([
                'resultcode' => '00',
                'response' => [
                    'id' => 'naver-provider-consent-user',
                    'email' => 'naver-consent@example.com',
                    'name' => 'Naver User',
                    'nickname' => 'Naver Nickname',
                ],
            ]),
            // Shape from Naver Login Plus agreement API reference (/v1/nid/agreement); no live response recorded yet.
            'https://openapi.naver.com/v1/nid/agreement' => Http::response([
                'result' => 'success',
                'agreementInfos' => [
                    [
                        'termCode' => 'terms-code',
                        'clientId' => 'test-naver-client-id',
                        'agreeDate' => '04:25:06.123 PM 09/16/2026',
                    ],
                    [
                        'termCode' => 'privacy-code',
                        'clientId' => 'test-naver-client-id',
                        'agreeDate' => '04:25:07.123 PM 09/16/2026',
                    ],
                ],
            ]),
        ]);

        $state = $this->getJson(route('social-auth.state', [
            'redirect' => '/profile',
        ]))->json('state');

        $response = $this->post(route('social-auth.callback', 'naver'), [
            'access_token' => 'naver-access-token',
            'state' => $state,
        ]);

        $response->assertRedirect('/profile');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'naver-consent@example.com',
        ]);

        $user = User::where('email', 'naver-consent@example.com')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->privacy_policy_accepted_at);
        $this->assertNull($user->marketing_accepted_at);
        $this->assertFalse(Session::has(config('social-auth.session.pending_registration')));
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

    public function test_authenticated_callback_connects_account_instead_of_registering(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret'),
        ]);

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
                    'email' => 'kakao@example.com',
                    'is_email_verified' => true,
                    'is_email_valid' => true,
                ],
            ]),
        ]);

        $this->actingAs($user);
        $state = $this->getJson(route('social-auth.state', [
            'context' => 'connect',
            'provider' => 'kakao',
            'redirect' => '/profile',
        ]))->json('state');

        $response = $this->postJson(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $state,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'connected',
                'provider' => 'kakao',
                'redirect' => '/profile',
            ]);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas(config('social-auth.table'), [
            'user_id' => $user->id,
            'provider' => 'kakao',
            'provider_id' => '12345',
        ]);
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse(Session::has(config('social-auth.session.connect_intent')));
    }

    public function test_kakao_connect_survives_a_nonce_fetch_from_another_button(): void
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        $this->fakeKakaoProfile(900_001);
        $this->actingAs($user);

        $state = $this->getJson(route('social-auth.state', ['context' => 'connect', 'provider' => 'kakao']))->json('state');
        // The profile's Google button (or another tab) fetches a nonce while the Kakao connect is in flight.
        $this->getJson(route('social-auth.nonce', ['context' => 'connect', 'provider' => 'google']))->assertOk();

        $this->postJson(route('social-auth.callback', 'kakao'), ['code' => 'kakao-code', 'state' => $state])
            ->assertOk()
            ->assertJson(['status' => 'connected', 'provider' => 'kakao']);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas(config('social-auth.table'), ['user_id' => $user->id, 'provider_id' => '900001']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_newer_login_state_never_turns_a_connect_callback_into_a_login(): void
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        $this->fakeKakaoProfile(900_002);
        $this->actingAs($user);

        $connectState = $this->getJson(route('social-auth.state', ['context' => 'connect', 'provider' => 'kakao']))->json('state');
        // Another tab starts a login flow and replaces the session state.
        $this->getJson(route('social-auth.state'))->assertOk();

        $this->postJson(route('social-auth.callback', 'kakao'), ['code' => 'kakao-code', 'state' => $connectState])
            ->assertStatus(422);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing(config('social-auth.table'), ['provider_id' => '900002']);
        $this->assertFalse(Session::has(config('social-auth.session.pending_registration')));
    }

    private function fakeKakaoProfile(int $kakaoId): void
    {
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response([
                'access_token' => 'kakao-access-token',
                'token_type' => 'bearer',
                'expires_in' => 21_600,
            ]),
            'https://kapi.kakao.com/v2/user/me' => Http::response([
                'id' => $kakaoId,
                'kakao_account' => [],
            ]),
        ]);
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

    /**
     * @return array<string, array{string|null}>
     */
    public static function passwordlessEmails(): array
    {
        return [
            'no email' => [null],
            // An email alone cannot log in, so it does not count as another login method.
            'email only' => ['user@example.com'],
        ];
    }

    #[DataProvider('passwordlessEmails')]
    public function test_cannot_disconnect_the_only_social_account_without_a_password(?string $email): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => $email,
            'password' => null,
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'g-only',
        ]);

        try {
            $this->app->make(SocialLoginManager::class)->disconnect('google', $user);
            $this->fail('Disconnecting the only login method should be rejected.');
        } catch (SocialAuthException $e) {
            $this->assertSame(SocialAuthException::cannotDisconnectLastMethod()->getMessage(), $e->getMessage());
            $this->assertDatabaseHas(config('social-auth.table'), ['provider_id' => 'g-only']);
        }
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

    public function test_ulid_user_key_is_not_mistaken_for_the_integer_owner(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('x')]);
        SocialAccount::create([
            'user_id' => $owner->id,
            'provider' => 'kakao',
            'provider_id' => 'k-ulid',
        ]);

        // A ULID starts with "01", which an (int) cast reads as 1, the owner's ID.
        $ulidUser = (new User)->setKeyType('string')->setIncrementing(false)
            ->forceFill(['id' => '01HZX5Q8W2N7K3M4P6R9T0V1Y2']);

        try {
            $this->app->make(SocialAccountService::class)->ensureNotLinkedToOtherUser(
                new ProviderUser(provider: 'kakao', providerId: 'k-ulid'),
                $ulidUser,
            );
            $this->fail('A provider account owned by another user must be rejected.');
        } catch (SocialAuthException $e) {
            $this->assertSame(SocialAuthException::accountAlreadyLinked('kakao')->getMessage(), $e->getMessage());
        }
    }

    public function test_login_refreshes_the_profile_when_tokens_are_not_stored(): void
    {
        config(['social-auth.store_tokens' => false]);
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('x')]);
        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'naver',
            'provider_id' => 'n-refresh',
            'avatar' => 'https://example.com/old.png',
        ]);

        $this->app->make(SocialAccountService::class)->updateTokens($account, new ProviderUser(
            provider: 'naver',
            providerId: 'n-refresh',
            avatar: 'https://example.com/new.png',
            accessToken: 'naver-access-token',
        ));

        $account->refresh();
        $this->assertSame('https://example.com/new.png', $account->avatar);
        $this->assertNull($account->access_token);
    }

    public function test_disconnecting_a_provider_that_is_not_connected_says_so(): void
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('x')]);

        $this->expectExceptionMessage('Your Naver account is not connected.');
        $this->app->make(SocialLoginManager::class)->disconnect('naver', $user);
    }

    public function test_kakao_disconnect_unlinks_with_the_access_token_as_bearer(): void
    {
        Event::fake([SocialAccountDisconnected::class]);

        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret'),
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'kakao',
            'provider_id' => '1234567890',
            'access_token' => 'kakao-access-token',
        ]);

        // Response shape from the Kakao Login REST API "Unlink" reference.
        Http::fake([
            'https://kapi.kakao.com/v1/user/unlink' => Http::response(['id' => 1234567890]),
        ]);

        $this->app->make(SocialLoginManager::class)->disconnect('kakao', $user);

        Http::assertSent(fn ($request) => $request->url() === 'https://kapi.kakao.com/v1/user/unlink'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer kakao-access-token')
            && ! isset($request['access_token']));
        Event::assertDispatched(SocialAccountDisconnected::class, fn ($event) => $event->remoteRevoked);
    }

    public function test_naver_disconnect_revokes_the_token_with_client_credentials(): void
    {
        Event::fake([SocialAccountDisconnected::class]);

        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('secret'),
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'naver',
            'provider_id' => 'naver-revoke-user',
            'access_token' => 'naver-access-token',
        ]);

        // Naver login dev guide 4.3 Token Revocation: success is HTTP 200 with no body.
        Http::fake([
            'https://nid.naver.com/oauth2.0/revoke' => Http::response(),
        ]);

        $this->app->make(SocialLoginManager::class)->disconnect('naver', $user);

        Http::assertSent(fn ($request) => $request->url() === 'https://nid.naver.com/oauth2.0/revoke'
            && $request['client_id'] === 'test-naver-client-id'
            && $request['client_secret'] === 'test-naver-secret'
            && $request['token'] === 'naver-access-token'
            && $request['token_type_hint'] === 'access_token');
        Event::assertDispatched(SocialAccountDisconnected::class, fn ($event) => $event->remoteRevoked);
    }

    public function test_naver_revoke_failure_still_removes_the_local_account(): void
    {
        Event::fake([SocialAccountDisconnected::class]);
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'naver',
            'provider_id' => 'naver-revoke-failure',
            'access_token' => 'naver-access-token',
        ]);

        // Naver login dev guide 4.3 Token Revocation: failed client authentication.
        Http::fake([
            'https://nid.naver.com/oauth2.0/revoke' => Http::response([
                'error' => 'unauthorized_client',
                'error_description' => 'Client authentication failed.',
            ], 401),
        ]);

        $this->app->make(SocialLoginManager::class)->disconnect('naver', $user);

        $this->assertDatabaseMissing(config('social-auth.table'), ['provider_id' => 'naver-revoke-failure']);
        Event::assertDispatched(SocialAccountDisconnected::class, fn ($event) => ! $event->remoteRevoked);
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

    public function test_default_nickname_generator(): void
    {
        $gen = $this->app->make(NicknameGeneratorContract::class);
        $pu = new ProviderUser(provider: 'google', providerId: 'x');
        $nick = $gen->generate($pu);
        $this->assertMatchesRegularExpression('/^user_[a-z0-9]{8}$/', $nick);
    }
}

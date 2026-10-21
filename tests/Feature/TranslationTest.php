<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Events\SocialLoginFailed;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class TranslationTest extends TestCase
{
    /**
     * @return array<string, array{string, Closure(): SocialAuthException, string}>
     */
    public static function factoryMessages(): array
    {
        $factories = [
            'invalidConfiguration' => [
                fn () => SocialAuthException::invalidConfiguration('bad value'),
                'Social Auth configuration error: bad value',
                'SNS 로그인 설정에 문제가 있습니다. (bad value)',
            ],
            'providerNotEnabled' => [
                fn () => SocialAuthException::providerNotEnabled('kakao'),
                'Provider [Kakao] is not enabled.',
                '카카오 로그인을 사용할 수 없습니다.',
            ],
            'providerNotFound' => [
                fn () => SocialAuthException::providerNotFound('apple'),
                'Provider [Apple] is not registered.',
                '애플 로그인을 사용할 수 없습니다.',
            ],
            'verificationFailed' => [
                fn () => SocialAuthException::verificationFailed('google', 'Token expired'),
                'Verification failed for provider [Google]',
                '구글 인증에 실패했습니다. 다시 시도해 주세요.',
            ],
            'providerRequestFailed' => [
                fn () => SocialAuthException::providerRequestFailed('naver', 'Failed to fetch user profile'),
                'Verification failed for provider [Naver]',
                '네이버 인증에 실패했습니다. 다시 시도해 주세요.',
            ],
            'accountAlreadyLinked' => [
                fn () => SocialAuthException::accountAlreadyLinked('kakao'),
                'This Kakao account is already linked to another user.',
                '이 카카오 계정은 이미 다른 회원 계정에 연결되어 있습니다.',
            ],
            'alreadyConnected' => [
                fn () => SocialAuthException::alreadyConnected('naver'),
                'You already have a Naver account connected.',
                '이미 네이버 계정이 연결되어 있습니다.',
            ],
            'notConnected' => [
                fn () => SocialAuthException::notConnected('kakao'),
                'Your Kakao account is not connected.',
                '카카오 계정이 연결되어 있지 않습니다.',
            ],
            'cannotDisconnectLastMethod' => [
                fn () => SocialAuthException::cannotDisconnectLastMethod(),
                'Cannot disconnect the last login method. Please set a password or connect another account first.',
                '마지막 로그인 수단은 연결을 해제할 수 없습니다. 먼저 비밀번호를 설정하거나 다른 SNS 계정을 연결해 주세요.',
            ],
            'consentRequired' => [
                fn () => SocialAuthException::consentRequired(),
                'You must accept the required terms and conditions to complete registration.',
                '회원가입을 완료하려면 필수 약관에 동의해 주세요.',
            ],
            'emailAlreadyRegistered' => [
                fn () => SocialAuthException::emailAlreadyRegistered(),
                'This email address is already registered. Sign in to your existing account, then connect this social account from your profile.',
                '이미 가입된 이메일 주소입니다. 기존 계정으로 로그인한 후 프로필에서 이 SNS 계정을 연결해 주세요.',
            ],
            'providerConsentRequired' => [
                fn () => SocialAuthException::providerConsentRequired('naver'),
                'We could not confirm the required terms consent from Naver. Please try again.',
                '네이버에서 필수 약관 동의를 확인하지 못했습니다. 다시 시도해 주세요.',
            ],
            'unauthenticated' => [
                fn () => SocialAuthException::unauthenticated(),
                'Authentication required.',
                '로그인이 필요합니다.',
            ],
            'invalidState' => [
                fn () => SocialAuthException::invalidState(),
                'Invalid or missing state parameter. Please try again.',
                '요청이 만료되었습니다. 다시 시도해 주세요.',
            ],
            'invalidNonce' => [
                fn () => SocialAuthException::invalidNonce(),
                'Invalid or missing nonce. Please try again.',
                '요청이 만료되었습니다. 다시 시도해 주세요.',
            ],
        ];

        $cases = [];
        foreach ($factories as $name => [$factory, $en, $ko]) {
            $cases["{$name} (en)"] = ['en', $factory, $en];
            $cases["{$name} (ko)"] = ['ko', $factory, $ko];
        }

        return $cases;
    }

    /**
     * @param  Closure(): SocialAuthException  $factory
     */
    #[DataProvider('factoryMessages')]
    public function test_factory_message_is_translated(string $locale, Closure $factory, string $expected): void
    {
        app()->setLocale($locale);

        $this->assertSame($expected, $factory()->getMessage());
    }

    public function test_unknown_provider_falls_back_to_capitalized_key(): void
    {
        $this->assertSame('Provider [Line] is not registered.', SocialAuthException::providerNotFound('line')->getMessage());
    }

    public function test_technical_reason_is_kept_out_of_the_message(): void
    {
        $verification = SocialAuthException::verificationFailed('kakao', 'Token exchange failed');
        $request = SocialAuthException::providerRequestFailed('apple', 'Authorization code exchange failed');

        $this->assertStringNotContainsString('Token exchange failed', $verification->getMessage());
        $this->assertSame('Token exchange failed', $verification->reason());
        $this->assertStringNotContainsString('Authorization code exchange failed', $request->getMessage());
        $this->assertSame('Authorization code exchange failed', $request->reason());
        $this->assertNull(SocialAuthException::verificationFailed('kakao')->reason());
    }

    public function test_social_login_failed_event_still_receives_the_reason(): void
    {
        Event::fake([SocialLoginFailed::class]);
        app()->setLocale('ko');
        Http::fake([
            'https://kauth.kakao.com/oauth/token' => Http::response(null, 503),
        ]);

        $state = $this->getJson(route('social-auth.state'))->json('state');
        $response = $this->postJson(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $state,
        ]);

        $response->assertStatus(422)->assertJson(['error' => '카카오 인증에 실패했습니다. 다시 시도해 주세요.']);
        Event::assertDispatched(
            SocialLoginFailed::class,
            fn (SocialLoginFailed $event): bool => $event->exception instanceof SocialAuthException
                && $event->exception->reason() === 'Token exchange failed',
        );
    }

    public function test_connect_success_flash_is_translated(): void
    {
        app()->setLocale('ko');
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        $this->fakeKakao();
        $this->actingAs($user);
        $state = $this->getJson(route('social-auth.state'))->json('state');

        $response = $this->post(route('social-auth.connect', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $state,
        ]);

        $response->assertSessionHas('success', '카카오 계정이 연결되었습니다.');
    }

    public function test_callback_connect_success_flash_is_translated(): void
    {
        app()->setLocale('ko');
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        $this->fakeKakao();
        $this->actingAs($user);
        $state = $this->getJson(route('social-auth.state', [
            'context' => 'connect',
            'provider' => 'kakao',
        ]))->json('state');

        $response = $this->post(route('social-auth.callback', 'kakao'), [
            'code' => 'kakao-code',
            'state' => $state,
        ]);

        $response->assertSessionHas('success', '카카오 계정이 연결되었습니다.');
    }

    public function test_disconnect_success_flash_is_translated(): void
    {
        app()->setLocale('ko');
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'naver', 'provider_id' => 'n-1']);

        $response = $this->actingAs($user)->delete(route('social-auth.disconnect', 'naver'));

        $response->assertSessionHas('success', '네이버 계정 연결이 해제되었습니다.');
    }

    public function test_english_success_flashes_are_unchanged(): void
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'naver', 'provider_id' => 'n-1']);

        $response = $this->actingAs($user)->delete(route('social-auth.disconnect', 'naver'));

        $response->assertSessionHas('success', 'Naver account disconnected.');
    }

    public function test_application_override_takes_precedence(): void
    {
        $directory = $this->app->langPath('vendor/social-auth/ko');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/messages.php', "<?php\n\nreturn ['unauthenticated' => '먼저 로그인해 주세요.'];\n");

        try {
            app()->setLocale('ko');

            $this->assertSame('먼저 로그인해 주세요.', SocialAuthException::unauthenticated()->getMessage());
            // Keys the application does not override still come from the package.
            $this->assertSame('요청이 만료되었습니다. 다시 시도해 주세요.', SocialAuthException::invalidState()->getMessage());
        } finally {
            File::deleteDirectory($this->app->langPath('vendor/social-auth'));
        }
    }

    private function fakeKakao(): void
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
                    'email' => 'kakao@example.com',
                    'is_email_verified' => true,
                    'is_email_valid' => true,
                ],
            ]),
        ]);
    }
}

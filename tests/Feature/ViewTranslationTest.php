<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\DataProvider;

class ViewTranslationTest extends TestCase
{
    public function test_en_and_ko_view_files_define_the_same_keys(): void
    {
        $root = dirname(__DIR__, 2).'/lang';
        $en = array_keys(Arr::dot(require $root.'/en/views.php'));
        $ko = array_keys(Arr::dot(require $root.'/ko/views.php'));

        $this->assertSame($en, $ko);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function buttonLabels(): array
    {
        return [
            'google login (en)' => ['en', 'google', 'login', 'Sign in with Google'],
            'google register (en)' => ['en', 'google', 'register', 'Sign up with Google'],
            'google connect (en)' => ['en', 'google', 'connect', 'Connect Google account'],
            'kakao login (en)' => ['en', 'kakao', 'login', 'Login with Kakao'],
            'kakao register (en)' => ['en', 'kakao', 'register', 'Sign up with Kakao'],
            'kakao connect (en)' => ['en', 'kakao', 'connect', 'Connect Kakao account'],
            'naver login (en)' => ['en', 'naver', 'login', 'Log in with NAVER'],
            'naver register (en)' => ['en', 'naver', 'register', 'Sign up with NAVER'],
            'naver connect (en)' => ['en', 'naver', 'connect', 'Connect NAVER account'],
            'google login (ko)' => ['ko', 'google', 'login', 'Google로 로그인'],
            'google register (ko)' => ['ko', 'google', 'register', 'Google로 시작하기'],
            'google connect (ko)' => ['ko', 'google', 'connect', 'Google 계정 연결'],
            'kakao login (ko)' => ['ko', 'kakao', 'login', '카카오 로그인'],
            'kakao register (ko)' => ['ko', 'kakao', 'register', '카카오로 시작하기'],
            'kakao connect (ko)' => ['ko', 'kakao', 'connect', '카카오 계정 연결'],
            'naver login (ko)' => ['ko', 'naver', 'login', '네이버 아이디로 로그인'],
            'naver register (ko)' => ['ko', 'naver', 'register', '네이버로 시작하기'],
            'naver connect (ko)' => ['ko', 'naver', 'connect', '네이버 계정 연결'],
        ];
    }

    #[DataProvider('buttonLabels')]
    public function test_button_label_is_translated(string $locale, string $provider, string $context, string $expected): void
    {
        app()->setLocale($locale);

        $html = view('social-auth::components.button', [
            'provider' => $provider,
            'context' => $context,
        ])->render();

        $this->assertStringContainsString($expected, $html);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function consentCopy(): array
    {
        return [
            'en' => ['en', [
                'Sign up with Kakao',
                'Review and accept the terms',
                'Please review and accept the terms below to start using the service.',
                'Select all',
                'Terms of service consent',
                '(Required)',
                '(Optional)',
                'View full text',
                'Agree and complete sign-up',
                'Terms of Service',
                'Privacy Policy',
                'Marketing communications',
            ]],
            'ko' => ['ko', [
                '카카오 계정으로 가입',
                '약관에 동의해 주세요',
                '서비스 이용을 시작하려면 아래 약관을 확인하고 동의해 주세요.',
                '모두 선택',
                '서비스 약관 동의',
                '(필수)',
                '(선택)',
                '원문 보기',
                '동의하고 가입 완료',
                '이용약관',
                '개인정보 처리방침',
                '마케팅 정보 수신 동의',
            ]],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('consentCopy')]
    public function test_consent_page_is_translated(string $locale, array $expected): void
    {
        app()->setLocale($locale);
        Session::put(config('social-auth.session.pending_registration'), [
            'provider' => 'kakao',
            'provider_id' => 'x',
            'email' => null,
            'email_verified' => false,
        ]);

        $response = $this->get(route('social-auth.consent'));

        $response->assertOk();
        foreach ($expected as $text) {
            $response->assertSee($text);
        }
    }

    public function test_configured_consent_label_overrides_the_translation(): void
    {
        app()->setLocale('en');
        config(['social-auth.consent.required_terms.terms_of_service.label' => 'Acme Terms']);
        Session::put(config('social-auth.session.pending_registration'), [
            'provider' => 'kakao',
            'provider_id' => 'x',
            'email' => null,
            'email_verified' => false,
        ]);

        $response = $this->get(route('social-auth.consent'));

        $response->assertOk();
        $response->assertSee('Acme Terms');
        $response->assertDontSee('Terms of Service');
        $response->assertSee('Privacy Policy');
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function connectedAccountsCopy(): array
    {
        return [
            'en' => ['en', ['Connected social accounts', 'Naver', 'Kakao', 'Connected', 'Not connected', 'Disconnect']],
            'ko' => ['ko', ['연결된 SNS 계정', '네이버', '카카오', '연결됨', '미연결', '연결 해제']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('connectedAccountsCopy')]
    public function test_connected_accounts_is_translated(string $locale, array $expected): void
    {
        app()->setLocale($locale);
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'naver', 'provider_id' => 'naver-user']);

        $this->actingAs($user);
        $html = preg_replace(['/>\s+/', '/\s+</'], ['>', '<'], view('social-auth::components.connected-accounts')->render());

        foreach ($expected as $text) {
            $this->assertStringContainsString('>'.$text.'<', $html);
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function naverCallbackCopy(): array
    {
        return [
            'en' => ['en', 'Checking your sign-in details.', 'Could not complete Naver sign-in.'],
            'ko' => ['ko', '로그인 정보를 확인하고 있습니다.', '네이버 로그인을 완료하지 못했습니다.'],
        ];
    }

    #[DataProvider('naverCallbackCopy')]
    public function test_naver_callback_page_is_translated(string $locale, string $checking, string $failed): void
    {
        app()->setLocale($locale);

        $response = $this->get(route('social-auth.callback', 'naver'));

        $response->assertOk();
        $response->assertSee($checking);
        $response->assertSee(json_encode($failed), false);
    }
}

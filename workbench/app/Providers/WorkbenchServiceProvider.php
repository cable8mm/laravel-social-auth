<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Config::set('cache.default', 'array');
        Config::set('social-auth.user_model', User::class);
        Config::set('auth.providers.users.model', User::class);

        if (! app()->environment('testing')) {
            return;
        }

        $env = static fn (string $key, string $fallback): string => (string) (getenv($key) ?: env($key, $fallback));

        Config::set('social-auth.providers.google.enabled', true);
        Config::set('social-auth.providers.google.client_id', $env('GOOGLE_CLIENT_ID', 'dusk-google-client-id'));
        Config::set('social-auth.providers.kakao.enabled', true);
        Config::set('social-auth.providers.kakao.client_id', $env('KAKAO_REST_API_KEY', 'dusk-kakao-client-id'));
        Config::set('social-auth.providers.kakao.js_client_id', $env('KAKAO_JAVASCRIPT_KEY', 'dusk-kakao-javascript-key'));
        Config::set('social-auth.providers.naver.enabled', true);
        Config::set('social-auth.providers.naver.client_id', $env('NAVER_CLIENT_ID', 'dusk-naver-client-id'));
        Config::set(
            'social-auth.consent.providers.naver.enabled',
            filter_var($env('NAVER_LOGIN_PLUS', 'false'), FILTER_VALIDATE_BOOLEAN),
        );
        Config::set(
            'social-auth.consent.providers.naver.term_codes.terms_of_service',
            $env('NAVER_TERMS_OF_SERVICE_CODE', ''),
        );
        Config::set(
            'social-auth.consent.providers.naver.term_codes.privacy_policy',
            $env('NAVER_PRIVACY_POLICY_CODE', ''),
        );
        Config::set(
            'social-auth.consent.providers.naver.term_codes.marketing',
            $env('NAVER_MARKETING_CODE', ''),
        );
        Config::set('social-auth.button_order', ['naver', 'kakao', 'google']);
    }
}

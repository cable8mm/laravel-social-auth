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
        Config::set('social-auth.providers.google.enabled', true);
        Config::set('social-auth.providers.google.client_id', 'dusk-google-client-id');
        Config::set('social-auth.providers.kakao.enabled', true);
        Config::set('social-auth.providers.kakao.client_id', 'dusk-kakao-client-id');
        Config::set('social-auth.providers.naver.enabled', true);
        Config::set('social-auth.providers.naver.client_id', 'dusk-naver-client-id');
        Config::set('social-auth.button_order', ['naver', 'kakao', 'google']);
    }
}

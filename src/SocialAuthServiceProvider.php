<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth;

use Cable8mm\LaravelSocialAuth\Console\InstallCommand;
use Cable8mm\LaravelSocialAuth\Contracts\NicknameGeneratorContract;
use Cable8mm\LaravelSocialAuth\Contracts\RegistrationConsentContract;
use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Services\DefaultNicknameGenerator;
use Cable8mm\LaravelSocialAuth\Services\RegistrationConsentService;
use Cable8mm\LaravelSocialAuth\Services\SocialAccountService;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class SocialAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/social-auth.php', 'social-auth');

        $this->app->singleton(SocialAccountService::class);
        $this->app->singleton(RegistrationConsentContract::class, RegistrationConsentService::class);

        $this->app->singleton(NicknameGeneratorContract::class, function ($app) {
            $generator = config('social-auth.nickname_generator');

            if ($generator === null) {
                return new DefaultNicknameGenerator;
            }

            if ($generator instanceof \Closure) {
                return new class($generator) implements NicknameGeneratorContract
                {
                    public function __construct(private readonly \Closure $closure) {}

                    public function generate(ProviderUser $providerUser): string
                    {
                        return ($this->closure)($providerUser);
                    }
                };
            }

            if (is_string($generator) && str_contains($generator, '@')) {
                return new class($generator) implements NicknameGeneratorContract
                {
                    public function __construct(private readonly string $callable) {}

                    public function generate(ProviderUser $providerUser): string
                    {
                        return app()->call($this->callable, ['providerUser' => $providerUser]);
                    }
                };
            }

            if (is_string($generator) && class_exists($generator)) {
                return $app->make($generator);
            }

            return new DefaultNicknameGenerator;
        });

        $this->app->singleton(SocialLoginManager::class, function ($app) {
            return new SocialLoginManager(
                $app->make(SocialAccountService::class),
                $app->make(RegistrationConsentContract::class),
                $app->make(NicknameGeneratorContract::class),
            );
        });
    }

    public function boot(): void
    {
        $this->validateConfiguration();

        $this->loadRoutesFrom(dirname(__DIR__).'/routes/web.php');
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'social-auth');
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);

            $this->publishes([
                dirname(__DIR__).'/config/social-auth.php' => config_path('social-auth.php'),
            ], 'social-auth-config');

            $this->publishes([
                dirname(__DIR__).'/resources/views' => resource_path('views/vendor/social-auth'),
            ], 'social-auth-views');

            $this->publishesMigrations([
                dirname(__DIR__).'/database/migrations/2024_01_01_000001_create_social_accounts_table.php' => database_path('migrations/2024_01_01_000001_create_social_accounts_table.php'),
            ], 'social-auth-migrations');

            $this->publishesMigrations([
                dirname(__DIR__).'/database/migrations/stubs/2024_01_01_000002_make_user_credentials_nullable.php' => database_path('migrations/2024_01_01_000002_make_user_credentials_nullable.php'),
            ], 'social-auth-user-columns');
        }

        Blade::componentNamespace('Cable8mm\\LaravelSocialAuth\\View\\Components', 'social-auth');

        // Register anonymous Blade components from package views
        Blade::anonymousComponentPath(dirname(__DIR__).'/resources/views/components', 'social-auth');
    }

    private function validateConfiguration(): void
    {
        $storeTokens = (bool) config('social-auth.store_tokens', true);
        $remoteRevoke = (bool) config('social-auth.remote_revoke', false);

        if (! $storeTokens && $remoteRevoke) {
            throw SocialAuthException::invalidConfiguration(
                'remote_revoke cannot be enabled when store_tokens is disabled. '.
                'Remote revoke requires stored access tokens. Set SOCIAL_AUTH_STORE_TOKENS=true or SOCIAL_AUTH_REMOTE_REVOKE=false.'
            );
        }
    }
}

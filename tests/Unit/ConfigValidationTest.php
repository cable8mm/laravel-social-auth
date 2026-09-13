<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Unit;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\SocialAuthServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class ConfigValidationTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        // Delay loading until we set config
        return [];
    }

    public function test_store_tokens_false_with_remote_revoke_true_throws_on_boot(): void
    {
        $this->expectException(SocialAuthException::class);
        $this->expectExceptionMessage('remote_revoke cannot be enabled when store_tokens is disabled');

        $this->app['config']->set('social-auth.store_tokens', false);
        $this->app['config']->set('social-auth.remote_revoke', true);

        // Manually boot the provider which runs validation
        $provider = new SocialAuthServiceProvider($this->app);
        $provider->register();
        $this->app['config']->set('social-auth.store_tokens', false);
        $this->app['config']->set('social-auth.remote_revoke', true);
        $provider->boot();
    }

    public function test_valid_combination_does_not_throw(): void
    {
        $this->app['config']->set('social-auth.store_tokens', true);
        $this->app['config']->set('social-auth.remote_revoke', true);

        $provider = new SocialAuthServiceProvider($this->app);
        $provider->register();
        $this->app['config']->set('social-auth.store_tokens', true);
        $this->app['config']->set('social-auth.remote_revoke', true);
        $provider->boot();

        $this->assertTrue(true);
    }
}

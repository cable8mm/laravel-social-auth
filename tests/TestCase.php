<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests;

use Cable8mm\LaravelSocialAuth\SocialAuthServiceProvider;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SocialAuthServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:2fl+KtvkdfkXgY0K8yO0Q8ZQ0zq0zq0zq0zq0zq0zq0=');

        $app['config']->set('social-auth.user_model', User::class);
        $app['config']->set('social-auth.providers.google.enabled', true);
        $app['config']->set('social-auth.providers.google.client_id', 'test-google-client-id');
        $app['config']->set('social-auth.providers.kakao.enabled', true);
        $app['config']->set('social-auth.providers.kakao.client_id', 'test-kakao-client-id');
        $app['config']->set('social-auth.providers.kakao.js_client_id', 'test-kakao-javascript-key');
        $app['config']->set('social-auth.providers.kakao.client_secret', 'test-kakao-secret');
        $app['config']->set('social-auth.providers.naver.enabled', true);
        $app['config']->set('social-auth.providers.naver.client_id', 'test-naver-client-id');
        $app['config']->set('social-auth.providers.naver.client_secret', 'test-naver-secret');
        $app['config']->set('social-auth.store_tokens', true);
        $app['config']->set('social-auth.remote_revoke', false);
        $app['config']->set('social-auth.protect_last_login_method', true);
        $app['config']->set('social-auth.button_order', ['naver', 'kakao', 'google']);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('nickname')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Run package migration
        $migration = require __DIR__.'/../database/migrations/2024_01_01_000001_create_social_accounts_table.php';
        $migration->up();
    }
}

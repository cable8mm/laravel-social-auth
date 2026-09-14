<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Support\Facades\File;

final class InstallCommandTest extends TestCase
{
    private string $publishedConfig;

    private string $publishedAsset;

    /** @var array<int, string> */
    private array $publishedMigrations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishedConfig = config_path('social-auth.php');
        $this->publishedAsset = resource_path('js/vendor/social-auth.js');
        $this->publishedMigrations = glob(database_path('migrations/*_make_user_credentials_nullable.php')) ?: [];

        File::delete($this->publishedConfig);
        File::delete($this->publishedAsset);
    }

    protected function tearDown(): void
    {
        File::delete($this->publishedConfig);
        File::delete($this->publishedAsset);

        foreach (glob(database_path('migrations/*_make_user_credentials_nullable.php')) ?: [] as $migration) {
            if (! in_array($migration, $this->publishedMigrations, true)) {
                File::delete($migration);
            }
        }

        parent::tearDown();
    }

    public function test_install_publishes_config_and_timestamped_user_columns_migration(): void
    {
        $this->artisan('social-auth:install')
            ->assertExitCode(0)
            ->expectsOutput('Laravel Social Auth installed successfully.');

        $this->assertFileExists($this->publishedConfig);
        $this->assertFileExists($this->publishedAsset);

        $migrations = glob(database_path('migrations/*_make_user_credentials_nullable.php')) ?: [];

        $this->assertCount(1, array_diff($migrations, $this->publishedMigrations));
    }

    public function test_install_does_not_run_migrations(): void
    {
        $this->artisan('social-auth:install')->assertExitCode(0);

        $this->assertFalse(
            collect(File::files(database_path('migrations')))
                ->contains(fn ($file): bool => str_contains($file->getFilename(), 'create_social_accounts_table'))
        );
    }
}

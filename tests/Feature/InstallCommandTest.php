<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Support\Facades\File;

final class InstallCommandTest extends TestCase
{
    private string $publishedConfig;

    /** @var array<int, string> */
    private array $publishedMigrations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishedConfig = config_path('social-auth.php');
        $this->publishedMigrations = glob(database_path('migrations/*_make_user_*_nullable.php')) ?: [];

        File::delete($this->publishedConfig);
    }

    protected function tearDown(): void
    {
        File::delete($this->publishedConfig);

        foreach (glob(database_path('migrations/*_make_user_*_nullable.php')) ?: [] as $migration) {
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
        $this->assertFileDoesNotExist(resource_path('js/vendor/social-auth.js'));

        $this->assertCount(1, $this->newlyPublished('credentials'));
        $this->assertCount(1, $this->newlyPublished('name'));
    }

    public function test_republishing_adds_only_the_name_migration_for_an_existing_install(): void
    {
        // An application that installed before the name migration existed has only the credentials one.
        $this->artisan('vendor:publish', ['--tag' => 'social-auth-user-columns'])->assertExitCode(0);
        File::delete($this->newlyPublished('name'));

        $this->artisan('vendor:publish', ['--tag' => 'social-auth-user-columns'])->assertExitCode(0);

        $this->assertCount(1, $this->newlyPublished('credentials'));
        $this->assertCount(1, $this->newlyPublished('name'));
    }

    /**
     * @return list<string>
     */
    private function newlyPublished(string $column): array
    {
        $migrations = glob(database_path("migrations/*_make_user_{$column}_nullable.php")) ?: [];

        return array_values(array_diff($migrations, $this->publishedMigrations));
    }

    public function test_install_tells_the_user_to_run_migrations(): void
    {
        $this->artisan('social-auth:install')
            ->assertExitCode(0)
            ->expectsOutputToContain('then run php artisan migrate');
    }
}

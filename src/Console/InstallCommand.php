<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'social-auth:install';

    protected $description = 'Install Laravel Social Auth configuration and user-column migration';

    public function handle(): int
    {
        $configPublish = $this->call('vendor:publish', [
            '--tag' => 'social-auth-config',
        ]);

        if ($configPublish !== self::SUCCESS) {
            return $configPublish;
        }

        $migrationPublish = $this->call('vendor:publish', [
            '--tag' => 'social-auth-user-columns',
        ]);

        if ($migrationPublish !== self::SUCCESS) {
            return $migrationPublish;
        }

        $this->newLine();
        $this->info('Laravel Social Auth installed successfully.');
        $this->line('Next steps: configure your provider credentials in .env, then run php artisan migrate.');

        return self::SUCCESS;
    }
}

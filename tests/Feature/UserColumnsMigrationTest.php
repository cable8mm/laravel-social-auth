<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UserColumnsMigrationTest extends TestCase
{
    public function test_user_columns_migration_can_make_email_and_password_nullable(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });

        $migration = require __DIR__.'/../../src/Database/Migrations/stubs/2024_01_01_000002_make_user_credentials_nullable.php';
        $migration->up();

        $columns = collect(Schema::getColumns('users'))->keyBy('name');

        $this->assertTrue($columns['email']['nullable']);
        $this->assertTrue($columns['password']['nullable']);
    }
}

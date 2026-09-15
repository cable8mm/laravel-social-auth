<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;
use Illuminate\Support\Facades\Schema;

class UserColumnsMigrationTest extends TestCase
{
    public function test_user_columns_migration_adds_nullable_user_columns(): void
    {
        Schema::table('users', function (SchemaBlueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });

        $migration = require __DIR__.'/../../database/migrations/stubs/2024_01_01_000002_make_user_credentials_nullable.php';
        $migration->up();

        $columns = collect(Schema::getColumns('users'))->keyBy('name');

        $this->assertTrue($columns['email']['nullable']);
        $this->assertTrue($columns['password']['nullable']);
        $this->assertArrayHasKey('terms_accepted_at', $columns);
        $this->assertArrayHasKey('privacy_policy_accepted_at', $columns);
        $this->assertArrayHasKey('marketing_accepted_at', $columns);
    }
}

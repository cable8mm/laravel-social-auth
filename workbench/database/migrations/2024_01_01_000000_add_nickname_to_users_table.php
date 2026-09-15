<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('nickname')->nullable();
            $table->string('password')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('privacy_policy_accepted_at')->nullable();
            $table->timestamp('marketing_accepted_at')->nullable();
        });

    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'nickname',
                'terms_accepted_at',
                'privacy_policy_accepted_at',
                'marketing_accepted_at',
            ]);
        });

    }
};

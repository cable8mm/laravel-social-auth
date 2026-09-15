<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'terms_accepted_at',
        'privacy_policy_accepted_at',
        'marketing_accepted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'terms_accepted_at' => 'datetime',
        'privacy_policy_accepted_at' => 'datetime',
        'marketing_accepted_at' => 'datetime',
    ];
}

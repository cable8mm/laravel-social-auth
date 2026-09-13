<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'email',
        'name',
        'nickname',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'raw',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'raw' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'raw',
    ];

    public function getTable(): string
    {
        return config('social-auth.table', 'social_accounts');
    }

    public function user(): BelongsTo
    {
        $userModel = config('social-auth.user_model');

        return $this->belongsTo($userModel, 'user_id');
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeForProviderId($query, string $provider, string $providerId)
    {
        return $query->where('provider', $provider)->where('provider_id', $providerId);
    }
}

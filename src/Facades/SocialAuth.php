<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Facades;

use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Cable8mm\LaravelSocialAuth\Contracts\ProviderContract provider(string $name)
 * @method static list<string> enabledProviders()
 * @method static array handleCallback(string $providerName, array $payload)
 * @method static array completeRegistration(array $consents)
 * @method static \Cable8mm\LaravelSocialAuth\Models\SocialAccount connect(string $providerName, array $payload, \Illuminate\Database\Eloquent\Model $user)
 * @method static void disconnect(string $providerName, \Illuminate\Database\Eloquent\Model $user)
 * @method static string generateNonce()
 * @method static string generateState()
 * @method static bool hasPendingRegistration()
 * @method static array|null getPendingRegistration()
 *
 * @see SocialLoginManager
 */
class SocialAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SocialLoginManager::class;
    }
}

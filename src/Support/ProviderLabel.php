<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Support;

use Illuminate\Support\Facades\Lang;

final class ProviderLabel
{
    /**
     * Translated provider name for user-facing messages, e.g. "kakao" -> "Kakao" / "카카오".
     */
    public static function for(string $provider): string
    {
        $key = "social-auth::providers.{$provider}";

        return Lang::has($key) ? (string) __($key) : ucfirst($provider);
    }
}

<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Services;

use Cable8mm\LaravelSocialAuth\Contracts\NicknameGeneratorContract;
use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Illuminate\Support\Str;

class DefaultNicknameGenerator implements NicknameGeneratorContract
{
    public function generate(ProviderUser $providerUser): string
    {
        return 'user_'.Str::lower(Str::random(8));
    }
}

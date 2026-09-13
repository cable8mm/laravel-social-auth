<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Contracts;

use Cable8mm\LaravelSocialAuth\Data\ProviderUser;

interface NicknameGeneratorContract
{
    public function generate(ProviderUser $providerUser): string;
}

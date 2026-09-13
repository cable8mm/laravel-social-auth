<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Events;

use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocialUserLoggedIn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly mixed $user,
        public readonly SocialAccount $socialAccount,
    ) {}
}

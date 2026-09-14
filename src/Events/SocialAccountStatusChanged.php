<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SocialAccountStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function __construct(
        public readonly string $provider,
        public readonly ?string $providerId,
        public readonly string $eventType,
        public readonly array $eventPayload,
    ) {}
}

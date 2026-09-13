<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Data;

final class ProviderUser
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $providerId,
        public readonly ?string $email = null,
        public readonly bool $emailVerified = false,
        public readonly ?string $name = null,
        public readonly ?string $nickname = null,
        public readonly ?string $avatar = null,
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?\DateTimeInterface $tokenExpiresAt = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_id' => $this->providerId,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_expires_at' => $this->tokenExpiresAt?->format(\DateTimeInterface::ATOM),
            'raw' => $this->raw,
        ];
    }
}

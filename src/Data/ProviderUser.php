<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Data;

final class ProviderUser
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, string>  $consents
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
        public readonly array $consents = [],
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
            'consents' => $this->consents,
        ];
    }

    /**
     * @param  array<string, string>  $consents
     */
    public function withConsents(array $consents): self
    {
        return new self(
            provider: $this->provider,
            providerId: $this->providerId,
            email: $this->email,
            emailVerified: $this->emailVerified,
            name: $this->name,
            nickname: $this->nickname,
            avatar: $this->avatar,
            accessToken: $this->accessToken,
            refreshToken: $this->refreshToken,
            tokenExpiresAt: $this->tokenExpiresAt,
            raw: $this->raw,
            consents: $consents,
        );
    }
}

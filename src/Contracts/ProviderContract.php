<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Contracts;

use Cable8mm\LaravelSocialAuth\Data\ProviderUser;

interface ProviderContract
{
    public function getName(): string;

    public function isEnabled(): bool;

    public function getClientId(): ?string;

    public function getJsSdkUrl(): string;

    public function getConfig(): array;

    /**
     * Verify the incoming credential/token/code and return a normalized ProviderUser.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): ProviderUser;

    /**
     * Attempt remote revoke of the access token (if supported).
     */
    public function revoke(?string $accessToken): bool;

    /**
     * Map provider profile fields to local user attributes.
     *
     * @return array{name?: string|null, nickname?: string|null, email?: string|null, avatar?: string|null}
     */
    public function mapProfile(ProviderUser $providerUser): array;
}

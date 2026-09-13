<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Providers;

use Cable8mm\LaravelSocialAuth\Contracts\ProviderContract;
use Cable8mm\LaravelSocialAuth\Data\ProviderUser;

abstract class AbstractProvider implements ProviderContract
{
    public function __construct(
        protected readonly array $config,
    ) {}

    abstract public function getName(): string;

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function getClientId(): ?string
    {
        return $this->config['client_id'] ?? null;
    }

    public function getJsClientId(): ?string
    {
        return $this->config['js_client_id'] ?? $this->getClientId();
    }

    public function getJsSdkUrl(): string
    {
        return $this->config['js_sdk_url'] ?? '';
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function mapProfile(ProviderUser $providerUser): array
    {
        $mapped = [
            'email' => $providerUser->email,
            'avatar' => $providerUser->avatar,
        ];

        $nameMapping = $this->config['name_mapping'] ?? null;
        if ($nameMapping === 'name') {
            $mapped['name'] = $providerUser->name;
        } elseif (is_string($nameMapping) && $nameMapping !== '') {
            $mapped['name'] = $providerUser->raw[$nameMapping] ?? $providerUser->name;
        } else {
            $mapped['name'] = null;
        }

        $nicknameMapping = $this->config['nickname_mapping'] ?? null;
        if ($nicknameMapping === 'nickname') {
            $mapped['nickname'] = $providerUser->nickname;
        } elseif (is_string($nicknameMapping) && $nicknameMapping !== '') {
            $mapped['nickname'] = $providerUser->raw[$nicknameMapping] ?? $providerUser->nickname;
        } else {
            $mapped['nickname'] = null;
        }

        return $mapped;
    }

    public function revoke(?string $accessToken): bool
    {
        return false;
    }

    /**
     * Sanitize raw data by removing sensitive tokens before storage.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function sanitizeRaw(array $raw): array
    {
        $sensitive = ['access_token', 'refresh_token', 'id_token', 'token', 'credential'];
        foreach ($sensitive as $key) {
            unset($raw[$key]);
        }

        return $raw;
    }
}

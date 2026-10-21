<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Services;

use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class SocialAccountService
{
    public function findByProvider(string $provider, string $providerId): ?SocialAccount
    {
        $model = config('social-auth.social_account_model', SocialAccount::class);

        return $model::query()
            ->forProviderId($provider, $providerId)
            ->first();
    }

    public function findForUser(Model $user, string $provider): ?SocialAccount
    {
        $model = config('social-auth.social_account_model', SocialAccount::class);

        return $model::query()
            ->where('user_id', $user->getKey())
            ->forProvider($provider)
            ->first();
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    public function listForUser(Model $user)
    {
        $model = config('social-auth.social_account_model', SocialAccount::class);

        return $model::query()
            ->where('user_id', $user->getKey())
            ->get();
    }

    public function createForUser(Model $user, ProviderUser $providerUser): SocialAccount
    {
        $model = config('social-auth.social_account_model', SocialAccount::class);
        $storeTokens = (bool) config('social-auth.store_tokens', true);

        /** @var SocialAccount $account */
        $account = $model::query()->create([
            'user_id' => $user->getKey(),
            'provider' => $providerUser->provider,
            'provider_id' => $providerUser->providerId,
            'email' => $providerUser->email,
            'name' => $providerUser->name,
            'nickname' => $providerUser->nickname,
            'avatar' => $providerUser->avatar,
            'access_token' => $storeTokens ? $providerUser->accessToken : null,
            'refresh_token' => $storeTokens ? $providerUser->refreshToken : null,
            'token_expires_at' => $storeTokens ? $providerUser->tokenExpiresAt : null,
            'raw' => $providerUser->raw,
        ]);

        return $account;
    }

    public function updateTokens(SocialAccount $account, ProviderUser $providerUser): void
    {
        $attributes = [
            'email' => $providerUser->email ?? $account->email,
            'name' => $providerUser->name ?? $account->name,
            'nickname' => $providerUser->nickname ?? $account->nickname,
            'avatar' => $providerUser->avatar ?? $account->avatar,
            'raw' => $providerUser->raw,
        ];

        // The profile is refreshed on every login; tokens only when the application stores them.
        if (config('social-auth.store_tokens', true)) {
            $attributes['access_token'] = $providerUser->accessToken;
            $attributes['refresh_token'] = $providerUser->refreshToken;
            $attributes['token_expires_at'] = $providerUser->tokenExpiresAt;
        }

        $account->update($attributes);
    }

    public function clearTokens(SocialAccount $account): void
    {
        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ]);
    }

    public function delete(SocialAccount $account): void
    {
        $account->delete();
    }

    public function ensureNotLinkedToOtherUser(ProviderUser $providerUser, ?Model $currentUser = null): void
    {
        $existing = $this->findByProvider($providerUser->provider, $providerUser->providerId);

        if ($existing === null) {
            return;
        }

        // Compared as strings so UUID/ULID user keys work as well as integer keys.
        if ($currentUser !== null && (string) $existing->user_id === (string) $currentUser->getKey()) {
            throw SocialAuthException::alreadyConnected($providerUser->provider);
        }

        throw SocialAuthException::accountAlreadyLinked($providerUser->provider);
    }

    public function canDisconnect(Model $user, SocialAccount $account): bool
    {
        if (! config('social-auth.protect_last_login_method', true)) {
            return true;
        }

        // An email alone cannot log in, so without a password the last social account is the only login method.
        return ! empty($user->getAttribute('password'))
            || $this->listForUser($user)->count() > 1;
    }
}

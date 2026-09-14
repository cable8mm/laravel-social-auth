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
        if (! config('social-auth.store_tokens', true)) {
            return;
        }

        $account->update([
            'access_token' => $providerUser->accessToken,
            'refresh_token' => $providerUser->refreshToken,
            'token_expires_at' => $providerUser->tokenExpiresAt,
            'email' => $providerUser->email ?? $account->email,
            'name' => $providerUser->name ?? $account->name,
            'nickname' => $providerUser->nickname ?? $account->nickname,
            'avatar' => $providerUser->avatar ?? $account->avatar,
            'raw' => $providerUser->raw,
        ]);
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

        if ($currentUser !== null && (int) $existing->user_id === (int) $currentUser->getKey()) {
            throw SocialAuthException::alreadyConnected($providerUser->provider);
        }

        if ($currentUser === null || (int) $existing->user_id !== (int) $currentUser->getKey()) {
            throw SocialAuthException::accountAlreadyLinked($providerUser->provider);
        }
    }

    public function canDisconnect(Model $user, SocialAccount $account): bool
    {
        if (! config('social-auth.protect_last_login_method', true)) {
            return true;
        }

        $password = $user->getAttribute('password');
        $email = $user->getAttribute('email');
        $accountsCount = $this->listForUser($user)->count();

        // Reject if this is the only login method
        if (empty($password) && empty($email) && $accountsCount <= 1) {
            return false;
        }

        // Also reject if password is empty and only one social account remains
        if (empty($password) && $accountsCount <= 1) {
            return false;
        }

        return true;
    }
}

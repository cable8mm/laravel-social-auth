<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Services;

use Cable8mm\LaravelSocialAuth\Contracts\NicknameGeneratorContract;
use Cable8mm\LaravelSocialAuth\Contracts\ProviderContract;
use Cable8mm\LaravelSocialAuth\Contracts\RegistrationConsentContract;
use Cable8mm\LaravelSocialAuth\Data\ProviderUser;
use Cable8mm\LaravelSocialAuth\Events\SocialAccountConnected;
use Cable8mm\LaravelSocialAuth\Events\SocialAccountDisconnected;
use Cable8mm\LaravelSocialAuth\Events\SocialUserLoggedIn;
use Cable8mm\LaravelSocialAuth\Events\SocialUserRegistered;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;
use Cable8mm\LaravelSocialAuth\Providers\AppleProvider;
use Cable8mm\LaravelSocialAuth\Providers\GoogleProvider;
use Cable8mm\LaravelSocialAuth\Providers\KakaoProvider;
use Cable8mm\LaravelSocialAuth\Providers\NaverProvider;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class SocialLoginManager
{
    /** @var array<string, ProviderContract> */
    private array $providers = [];

    public function __construct(
        private readonly SocialAccountService $accountService,
        private readonly RegistrationConsentContract $consentService,
        private readonly NicknameGeneratorContract $nicknameGenerator,
    ) {
        $this->bootProviders();
    }

    private function bootProviders(): void
    {
        $map = [
            'google' => GoogleProvider::class,
            'kakao' => KakaoProvider::class,
            'naver' => NaverProvider::class,
            'apple' => AppleProvider::class,
        ];

        foreach ($map as $name => $class) {
            $config = config("social-auth.providers.{$name}", []);
            $this->providers[$name] = new $class($config);
        }
    }

    public function provider(string $name): ProviderContract
    {
        if (! isset($this->providers[$name])) {
            throw SocialAuthException::providerNotFound($name);
        }

        return $this->providers[$name];
    }

    /**
     * @return list<string>
     */
    public function enabledProviders(): array
    {
        $order = config('social-auth.button_order', ['naver', 'kakao', 'google']);
        $enabled = [];

        foreach ($order as $name) {
            if (isset($this->providers[$name]) && $this->providers[$name]->isEnabled()) {
                $enabled[] = $name;
            }
        }

        // Include any enabled providers not in order
        foreach ($this->providers as $name => $provider) {
            if ($provider->isEnabled() && ! in_array($name, $enabled, true)) {
                $enabled[] = $name;
            }
        }

        return $enabled;
    }

    /**
     * Handle login / registration callback from provider.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: string, user?: Model, redirect?: string}
     */
    public function handleCallback(string $providerName, array $payload): array
    {
        $provider = $this->provider($providerName);

        if (! $provider->isEnabled()) {
            throw SocialAuthException::providerNotEnabled($providerName);
        }

        $providerUser = $provider->verify($payload);

        // Existing linked account → login
        $existingAccount = $this->accountService->findByProvider(
            $providerUser->provider,
            $providerUser->providerId
        );

        if ($existingAccount !== null) {
            $user = $existingAccount->user;
            $this->accountService->updateTokens($existingAccount, $providerUser);
            $this->loginUser($user);
            event(new SocialUserLoggedIn($user, $existingAccount));

            return [
                'status' => 'logged_in',
                'user' => $user,
                'redirect' => $this->consumeIntendedUrl(),
            ];
        }

        // No automatic merge by email — always go to pending registration
        $this->storePendingRegistration($providerUser);

        return [
            'status' => 'pending_consent',
            'redirect' => config('social-auth.redirects.registration_consent', '/social-auth/consent'),
        ];
    }

    /**
     * Complete registration after consent.
     *
     * @param  array<string, bool>  $consents
     */
    public function completeRegistration(array $consents): array
    {
        $pending = $this->getPendingRegistration();
        if ($pending === null) {
            throw SocialAuthException::verificationFailed('unknown', 'No pending registration found');
        }

        if (! $this->consentService->validate($consents)) {
            throw SocialAuthException::consentRequired();
        }

        $providerUser = $this->hydrateProviderUser($pending);

        // Double-check not linked in the meantime
        $this->accountService->ensureNotLinkedToOtherUser($providerUser);

        $user = DB::transaction(function () use ($providerUser, $consents) {
            $user = $this->createUser($providerUser);
            $account = $this->accountService->createForUser($user, $providerUser);
            event(new SocialUserRegistered($user, $account, $consents));

            return $user;
        });

        $this->clearPendingRegistration();
        $this->loginUser($user);

        $account = $this->accountService->findByProvider($providerUser->provider, $providerUser->providerId);

        return [
            'status' => 'registered',
            'user' => $user,
            'social_account' => $account,
            'redirect' => $this->consumeIntendedUrl(),
        ];
    }

    /**
     * Remember the page from which social authentication was started.
     */
    public function rememberIntendedUrl(?string $url): void
    {
        $middlewareUrl = Session::get('url.intended');
        if (is_string($middlewareUrl) && $middlewareUrl !== '') {
            $url = $middlewareUrl;
        }

        if (! is_string($url) || $url === '') {
            return;
        }

        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            if ($parsed['host'] !== request()->getHost()) {
                return;
            }

            $url = ($parsed['path'] ?? '/')
                .(isset($parsed['query']) ? '?'.$parsed['query'] : '')
                .(isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');
        }

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return;
        }

        Session::put(
            config('social-auth.session.intended', 'social_auth.intended'),
            $url,
        );
    }

    /**
     * Consume the intended URL after successful login or registration.
     */
    public function consumeIntendedUrl(): string
    {
        $url = Session::pull(config('social-auth.session.intended', 'social_auth.intended'))
            ?? Session::pull('url.intended');

        return is_string($url) && $url !== ''
            ? $url
            : config('social-auth.redirects.login_success', '/');
    }

    /**
     * Connect a social account to the currently authenticated user.
     *
     * @param  array<string, mixed>  $payload
     */
    public function connect(string $providerName, array $payload, Model $user): SocialAccount
    {
        $provider = $this->provider($providerName);

        if (! $provider->isEnabled()) {
            throw SocialAuthException::providerNotEnabled($providerName);
        }

        $providerUser = $provider->verify($payload);

        // Already connected to this user?
        if ($this->accountService->findForUser($user, $providerName) !== null) {
            throw SocialAuthException::alreadyConnected($providerName);
        }

        // Linked to someone else?
        $this->accountService->ensureNotLinkedToOtherUser($providerUser, $user);

        $account = $this->accountService->createForUser($user, $providerUser);
        event(new SocialAccountConnected($account, $user));

        return $account;
    }

    /**
     * Disconnect a social account from the user.
     */
    public function disconnect(string $providerName, Model $user): void
    {
        $account = $this->accountService->findForUser($user, $providerName);

        if ($account === null) {
            throw SocialAuthException::providerNotFound($providerName);
        }

        if (! $this->accountService->canDisconnect($user, $account)) {
            throw SocialAuthException::cannotDisconnectLastMethod();
        }

        $remoteRevoked = false;
        $shouldRemoteRevoke = (bool) config('social-auth.remote_revoke', false);

        if ($shouldRemoteRevoke && $account->access_token) {
            try {
                $provider = $this->provider($providerName);
                $remoteRevoked = $provider->revoke($account->access_token);
            } catch (\Throwable) {
                // Remote revoke failure — continue based on config; default is still delete local
                $remoteRevoked = false;
            }
        }

        $this->accountService->delete($account);
        event(new SocialAccountDisconnected($account, $user, $remoteRevoked));
    }

    public function generateNonce(): string
    {
        $nonce = Str::random(32);
        Session::put(config('social-auth.session.nonce', 'social_auth.nonce'), $nonce);

        return $nonce;
    }

    public function generateState(): string
    {
        $state = Str::random(40);
        Session::put(config('social-auth.session.state', 'social_auth.state'), $state);

        return $state;
    }

    public function storePendingRegistration(ProviderUser $providerUser): void
    {
        Session::put(
            config('social-auth.session.pending_registration', 'social_auth.pending_registration'),
            $providerUser->toArray()
        );
    }

    public function getPendingRegistration(): ?array
    {
        return Session::get(
            config('social-auth.session.pending_registration', 'social_auth.pending_registration')
        );
    }

    public function clearPendingRegistration(): void
    {
        Session::forget(
            config('social-auth.session.pending_registration', 'social_auth.pending_registration')
        );
    }

    public function hasPendingRegistration(): bool
    {
        return $this->getPendingRegistration() !== null;
    }

    private function createUser(ProviderUser $providerUser): Model
    {
        $userModel = config('social-auth.user_model');
        $provider = $this->provider($providerUser->provider);
        $mapped = $provider->mapProfile($providerUser);

        $nickname = $mapped['nickname'] ?? null;
        if (empty($nickname)) {
            $nickname = $this->nicknameGenerator->generate($providerUser);
        }

        $emailVerifiedAt = null;
        $policy = config('social-auth.email_verification_policy', 'provider_email_verified');

        if ($providerUser->email) {
            $emailVerifiedAt = match ($policy) {
                'always_verified' => now(),
                'never_verified' => null,
                default => $providerUser->emailVerified ? now() : null,
            };
        }

        /** @var Model $user */
        $user = $userModel::query()->create([
            'name' => $mapped['name'] ?? $nickname,
            'email' => $providerUser->email,
            'email_verified_at' => $emailVerifiedAt,
            'password' => null,
            'nickname' => $nickname,
        ]);

        return $user;
    }

    private function loginUser(Model $user): void
    {
        /** @var StatefulGuard $guard */
        $guard = Auth::guard();
        $guard->login($user, true);
        Session::regenerate();
    }

    private function hydrateProviderUser(array $data): ProviderUser
    {
        $expiresAt = null;
        if (! empty($data['token_expires_at'])) {
            $expiresAt = new \DateTimeImmutable($data['token_expires_at']);
        }

        return new ProviderUser(
            provider: $data['provider'],
            providerId: $data['provider_id'],
            email: $data['email'] ?? null,
            emailVerified: (bool) ($data['email_verified'] ?? false),
            name: $data['name'] ?? null,
            nickname: $data['nickname'] ?? null,
            avatar: $data['avatar'] ?? null,
            accessToken: $data['access_token'] ?? null,
            refreshToken: $data['refresh_token'] ?? null,
            tokenExpiresAt: $expiresAt,
            raw: $data['raw'] ?? [],
        );
    }
}

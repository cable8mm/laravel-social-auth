<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Data\ProviderError;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubTokenVerifier
{
    private const TOKEN_URL = 'https://github.com/login/oauth/access_token';

    private const USER_URL = 'https://api.github.com/user';

    private const EMAILS_URL = 'https://api.github.com/user/emails';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ?string $redirectUri,
    ) {}

    /**
     * Exchange authorization code for a token and fetch the user and verified primary email.
     *
     * @return array{tokens: array, profile: array, email: string|null}
     *
     * @throws SocialAuthException
     */
    public function exchangeCode(string $code): array
    {
        $payload = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
        ];

        if ($this->redirectUri) {
            $payload['redirect_uri'] = $this->redirectUri;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(self::TOKEN_URL, $payload);

        if ($response->serverError()) {
            throw SocialAuthException::providerRequestFailed('github', 'Token exchange failed', ProviderError::fromResponse($response, [$code, $this->clientSecret]));
        }

        // GitHub reports OAuth errors such as bad_verification_code with HTTP 200.
        if (! $response->successful() || $response->json('error') !== null) {
            throw SocialAuthException::verificationFailed('github', 'Token exchange failed', ProviderError::fromResponse($response, [$code, $this->clientSecret]));
        }

        $tokens = $response->json();

        if (empty($tokens['access_token'])) {
            throw SocialAuthException::verificationFailed('github', 'No access token received');
        }

        return [
            'tokens' => $tokens,
            'profile' => $this->fetchProfile($tokens['access_token']),
            'email' => $this->fetchVerifiedPrimaryEmail($tokens['access_token']),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SocialAuthException
     */
    public function fetchProfile(string $accessToken): array
    {
        $response = $this->api($accessToken)->get(self::USER_URL);

        if ($response->serverError()) {
            throw SocialAuthException::providerRequestFailed('github', 'Failed to fetch user profile', ProviderError::fromResponse($response, [$accessToken]));
        }

        if (! $response->successful()) {
            throw SocialAuthException::verificationFailed('github', 'Failed to fetch user profile', ProviderError::fromResponse($response, [$accessToken]));
        }

        $profile = $response->json();

        if (empty($profile['id'])) {
            throw SocialAuthException::verificationFailed('github', 'Invalid profile response');
        }

        return $profile;
    }

    /**
     * Only the primary email GitHub reports as verified is trusted; the public profile email is ignored.
     *
     * @throws SocialAuthException
     */
    public function fetchVerifiedPrimaryEmail(string $accessToken): ?string
    {
        $response = $this->api($accessToken)->get(self::EMAILS_URL);

        if ($response->serverError()) {
            throw SocialAuthException::providerRequestFailed('github', 'Failed to fetch user emails', ProviderError::fromResponse($response, [$accessToken]));
        }

        if (! $response->successful()) {
            throw SocialAuthException::verificationFailed('github', 'Failed to fetch user emails', ProviderError::fromResponse($response, [$accessToken]));
        }

        foreach ((array) $response->json() as $entry) {
            if (is_array($entry)
                && ($entry['primary'] ?? false) === true
                && ($entry['verified'] ?? false) === true
                && is_string($entry['email'] ?? null)) {
                return $entry['email'];
            }
        }

        return null;
    }

    public function revoke(string $accessToken): bool
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->accept('application/vnd.github+json')
            ->timeout(10)
            ->delete('https://api.github.com/applications/'.rawurlencode($this->clientId).'/grant', [
                'access_token' => $accessToken,
            ]);

        // GitHub returns 204 No Content when the grant is deleted.
        return $response->successful();
    }

    private function api(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->accept('application/vnd.github+json')
            ->timeout(15);
    }
}

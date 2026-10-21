<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Data\ProviderError;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Illuminate\Support\Facades\Http;

class KakaoTokenVerifier
{
    private const TOKEN_URL = 'https://kauth.kakao.com/oauth/token';

    private const USER_URL = 'https://kapi.kakao.com/v2/user/me';

    public function __construct(
        private readonly string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $redirectUri,
    ) {}

    /**
     * Exchange authorization code for tokens and fetch user profile.
     *
     * @return array{tokens: array, profile: array}
     *
     * @throws SocialAuthException
     */
    public function exchangeCode(string $code): array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'code' => $code,
        ];

        if ($this->clientSecret) {
            $payload['client_secret'] = $this->clientSecret;
        }

        if ($this->redirectUri) {
            $payload['redirect_uri'] = $this->redirectUri;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post(self::TOKEN_URL, $payload);

        if ($response->serverError()) {
            throw SocialAuthException::providerRequestFailed('kakao', 'Token exchange failed', ProviderError::fromResponse($response, [$code, $this->clientSecret]));
        }

        if (! $response->successful()) {
            throw SocialAuthException::verificationFailed('kakao', 'Token exchange failed', ProviderError::fromResponse($response, [$code, $this->clientSecret]));
        }

        $tokens = $response->json();

        if (empty($tokens['access_token'])) {
            throw SocialAuthException::verificationFailed('kakao', 'No access token received');
        }

        $profile = $this->fetchProfile($tokens['access_token']);

        return [
            'tokens' => $tokens,
            'profile' => $profile,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SocialAuthException
     */
    public function fetchProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->get(self::USER_URL);

        if ($response->serverError()) {
            throw SocialAuthException::providerRequestFailed('kakao', 'Failed to fetch user profile', ProviderError::fromResponse($response, [$accessToken]));
        }

        if (! $response->successful()) {
            throw SocialAuthException::verificationFailed('kakao', 'Failed to fetch user profile', ProviderError::fromResponse($response, [$accessToken]));
        }

        $profile = $response->json();

        if (empty($profile['id'])) {
            throw SocialAuthException::verificationFailed('kakao', 'Invalid profile response');
        }

        return $profile;
    }

    public function revoke(string $accessToken): bool
    {
        // Kakao unlink takes the user access token as a Bearer header and returns 200 with the user ID on success.
        $response = Http::withToken($accessToken)
            ->asForm()
            ->timeout(10)
            ->post('https://kapi.kakao.com/v1/user/unlink');

        return $response->successful();
    }
}

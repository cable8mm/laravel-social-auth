<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Verifiers;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Illuminate\Support\Facades\Http;

class NaverTokenVerifier
{
    private const PROFILE_URL = 'https://openapi.naver.com/v1/nid/me';

    private const AGREEMENT_URL = 'https://openapi.naver.com/v1/nid/agreement';

    /**
     * Verify access token by fetching Naver profile.
     *
     * @return array<string, mixed>
     *
     * @throws SocialAuthException
     */
    public function fetchProfile(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])
            ->timeout(15)
            ->get(self::PROFILE_URL);

        if (! $response->successful()) {
            throw SocialAuthException::verificationFailed('naver', 'Failed to fetch user profile');
        }

        $body = $response->json();

        if (($body['resultcode'] ?? '') !== '00' || empty($body['response']['id'])) {
            throw SocialAuthException::verificationFailed('naver', 'Invalid profile response');
        }

        return $body['response'];
    }

    public function revoke(string $accessToken, string $clientId, string $clientSecret): bool
    {
        $response = Http::asForm()
            ->timeout(10)
            ->post('https://nid.naver.com/oauth2.0/token', [
                'grant_type' => 'delete',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'access_token' => $accessToken,
                'service_provider' => 'NAVER',
            ]);

        return $response->successful();
    }

    /**
     * Fetch the user's service-term agreements from Naver Login Plus.
     *
     * @return array<string, string> Agreement date keyed by Naver term code
     *
     * @throws SocialAuthException
     */
    public function fetchAgreement(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])
            ->timeout(15)
            ->get(self::AGREEMENT_URL);

        if (! $response->successful()) {
            throw SocialAuthException::verificationFailed('naver', 'Failed to fetch service agreements');
        }

        $body = $response->json();
        if (($body['result'] ?? '') !== 'success' || ! is_array($body['agreementInfos'] ?? null)) {
            throw SocialAuthException::verificationFailed('naver', 'Invalid service agreement response');
        }

        $agreements = [];
        foreach ($body['agreementInfos'] as $agreement) {
            $termCode = $agreement['termCode'] ?? null;
            $agreeDate = $agreement['agreeDate'] ?? null;

            if (is_string($termCode) && $termCode !== '' && is_string($agreeDate) && $agreeDate !== '') {
                $agreements[$termCode] = $agreeDate;
            }
        }

        return $agreements;
    }
}

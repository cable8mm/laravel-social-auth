<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Feature;

use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Cable8mm\LaravelSocialAuth\Tests\Fixtures\User;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class CredentialNonceTest extends TestCase
{
    private const APPLE_CLIENT_ID = 'com.example.web';

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function googleCredential(array $claims): string
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        Cache::flush();
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => 'google-test-key',
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ]]]),
        ]);

        return JWT::encode(array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-google-client-id',
            'sub' => 'google-nonce-user',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $claims), $privateKey, 'RS256', 'google-test-key');
    }

    /**
     * Enables Apple with a throwaway EC key and returns a signed identity token.
     *
     * @param  array<string, mixed>  $claims
     */
    private function appleIdentityToken(array $claims): string
    {
        $resource = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        config([
            'social-auth.providers.apple.enabled' => true,
            'social-auth.providers.apple.client_id' => self::APPLE_CLIENT_ID,
            'social-auth.providers.apple.team_id' => 'team-id',
            'social-auth.providers.apple.key_id' => 'key-id',
            'social-auth.providers.apple.private_key' => $privateKey,
            'social-auth.providers.apple.redirect' => 'http://localhost/social-auth/apple/callback',
        ]);
        $this->app->forgetInstance(SocialLoginManager::class);

        Cache::flush();
        Http::fake([
            'https://appleid.apple.com/auth/keys' => Http::response(['keys' => [[
                'kty' => 'EC',
                'use' => 'sig',
                'alg' => 'ES256',
                'kid' => 'apple-test-key',
                'crv' => 'P-256',
                'x' => $this->base64UrlEncode(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
                'y' => $this->base64UrlEncode(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
            ]]]),
            'https://appleid.apple.com/auth/token' => Http::response([
                'access_token' => 'apple-access-token',
                'refresh_token' => 'apple-refresh-token',
                'expires_in' => 3600,
            ]),
        ]);

        return JWT::encode(array_merge([
            'iss' => 'https://appleid.apple.com',
            'aud' => self::APPLE_CLIENT_ID,
            'sub' => 'apple-nonce-user',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $claims), $privateKey, 'ES256', 'apple-test-key');
    }

    public function test_google_credential_with_the_session_nonce_is_accepted(): void
    {
        $nonce = $this->getJson(route('social-auth.nonce'))->json('nonce');
        $credential = $this->googleCredential(['nonce' => $nonce]);

        $this->postJson(route('social-auth.callback', 'google'), ['credential' => $credential])
            ->assertOk()
            ->assertJson(['status' => 'pending_consent']);
    }

    public function test_authenticated_google_credential_connects_through_the_connect_route(): void
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => bcrypt('secret')]);
        $this->actingAs($user);
        $nonce = $this->getJson(route('social-auth.nonce'))->json('nonce');
        $credential = $this->googleCredential(['nonce' => $nonce]);

        $this->postJson(route('social-auth.connect', 'google'), ['credential' => $credential])
            ->assertOk()
            ->assertJson(['status' => 'connected', 'provider' => 'google']);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas(config('social-auth.table'), [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-nonce-user',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_credential_without_a_session_nonce_is_rejected(): void
    {
        $credential = $this->googleCredential(['nonce' => 'nonce-issued-to-another-session']);

        $this->postJson(route('social-auth.callback', 'google'), ['credential' => $credential])
            ->assertStatus(422);

        $this->assertFalse(Session::has(config('social-auth.session.pending_registration')));
        Http::assertNothingSent();
    }

    public function test_apple_identity_token_without_a_session_nonce_is_rejected(): void
    {
        $identityToken = $this->appleIdentityToken(['nonce' => 'nonce-issued-to-another-session']);
        $state = $this->getJson(route('social-auth.state'))->json('state');

        $this->postJson(route('social-auth.callback', 'apple'), [
            'code' => 'attacker-authorization-code',
            'id_token' => $identityToken,
            'state' => $state,
        ])->assertStatus(422);

        $this->assertFalse(Session::has(config('social-auth.session.pending_registration')));
        Http::assertNothingSent();
    }

    public function test_apple_identity_token_with_a_different_nonce_is_rejected(): void
    {
        $identityToken = $this->appleIdentityToken(['nonce' => 'nonce-issued-to-another-session']);
        $state = $this->getJson(route('social-auth.state'))->json('state');
        $this->getJson(route('social-auth.nonce'))->assertOk();

        $this->postJson(route('social-auth.callback', 'apple'), [
            'code' => 'attacker-authorization-code',
            'id_token' => $identityToken,
            'state' => $state,
        ])->assertStatus(422);

        $this->assertFalse(Session::has(config('social-auth.session.pending_registration')));
        Http::assertNotSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/token');
    }

    public function test_apple_identity_token_with_the_session_nonce_is_accepted(): void
    {
        $state = $this->getJson(route('social-auth.state'))->json('state');
        $nonce = $this->getJson(route('social-auth.nonce'))->json('nonce');
        $identityToken = $this->appleIdentityToken(['nonce' => $nonce]);

        $this->postJson(route('social-auth.callback', 'apple'), [
            'code' => 'authorization-code',
            'id_token' => $identityToken,
            'state' => $state,
        ])->assertOk()->assertJson(['status' => 'pending_consent']);
    }
}

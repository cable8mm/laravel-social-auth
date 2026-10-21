<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Tests\Unit;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Tests\TestCase;
use Cable8mm\LaravelSocialAuth\Verifiers\GitHubTokenVerifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fake shapes follow GitHub's documented OAuth App and REST API schemas
 * (no live response recorded yet — see TASKS.md).
 */
class GitHubTokenVerifierTest extends TestCase
{
    public function test_code_exchange_returns_profile_and_verified_primary_email(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'gho_test',
                'scope' => 'read:user,user:email',
                'token_type' => 'bearer',
            ]),
            'https://api.github.com/user' => Http::response([
                'login' => 'octocat',
                'id' => 583231,
                'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=4',
                'name' => 'The Octocat',
                'email' => 'public@example.com',
            ]),
            'https://api.github.com/user/emails' => Http::response([
                ['email' => 'secondary@example.com', 'verified' => true, 'primary' => false, 'visibility' => null],
                ['email' => 'octocat@github.com', 'verified' => true, 'primary' => true, 'visibility' => 'public'],
            ]),
        ]);

        $result = (new GitHubTokenVerifier('client-id', 'client-secret', 'https://app.test/callback'))
            ->exchangeCode('github-code');

        $this->assertSame('gho_test', $result['tokens']['access_token']);
        $this->assertSame(583231, $result['profile']['id']);
        $this->assertSame('octocat@github.com', $result['email']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://github.com/login/oauth/access_token'
            && $request['code'] === 'github-code'
            && $request['redirect_uri'] === 'https://app.test/callback'
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_unverified_primary_email_is_not_used(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_test', 'token_type' => 'bearer']),
            'https://api.github.com/user' => Http::response(['login' => 'octocat', 'id' => 1, 'email' => 'public@example.com']),
            'https://api.github.com/user/emails' => Http::response([
                ['email' => 'octocat@github.com', 'verified' => false, 'primary' => true, 'visibility' => 'public'],
            ]),
        ]);

        $result = (new GitHubTokenVerifier('client-id', 'client-secret', null))->exchangeCode('code');

        $this->assertNull($result['email']);
    }

    public function test_oauth_error_returned_with_http_200_is_rejected(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response([
                'error' => 'bad_verification_code',
                'error_description' => 'The code passed is incorrect or expired.',
                'error_uri' => 'https://docs.github.com/apps/managing-oauth-apps/troubleshooting-oauth-app-access-token-request-errors/#bad-verification-code',
            ]),
        ]);

        try {
            (new GitHubTokenVerifier('client-id', 'client-secret', null))->exchangeCode('expired-code');
            $this->fail('Expected SocialAuthException');
        } catch (SocialAuthException $e) {
            $this->assertSame('invalid_credential', $e->failureReason());
            $this->assertSame('bad_verification_code', $e->providerError()?->error);
        }

        Http::assertSentCount(1);
    }

    public function test_server_error_is_a_provider_error(): void
    {
        Http::fake([
            'https://github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_test', 'token_type' => 'bearer']),
            'https://api.github.com/user' => Http::response(['message' => 'Server Error'], 502),
        ]);

        try {
            (new GitHubTokenVerifier('client-id', 'client-secret', null))->exchangeCode('code');
            $this->fail('Expected SocialAuthException');
        } catch (SocialAuthException $e) {
            $this->assertSame('provider_error', $e->failureReason());
            $this->assertSame('Server Error', $e->providerError()?->errorDescription);
        }
    }

    public function test_revoke_deletes_the_grant_with_basic_auth(): void
    {
        Http::fake([
            'https://api.github.com/applications/client-id/grant' => Http::response(null, 204),
        ]);

        $this->assertTrue((new GitHubTokenVerifier('client-id', 'client-secret', null))->revoke('gho_test'));

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret'))
            && $request['access_token'] === 'gho_test');
    }
}

<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class SocialLoginController extends Controller
{
    public function __construct(
        private readonly SocialLoginManager $manager,
    ) {}

    public function nonce(Request $request): JsonResponse
    {
        $this->manager->rememberIntendedUrl($request->query('redirect'));
        $this->rememberConnectingProvider($request);

        return response()->json(['nonce' => $this->manager->generateNonce()]);
    }

    public function state(Request $request): JsonResponse
    {
        $this->manager->rememberIntendedUrl($request->query('redirect'));
        $this->rememberConnectingProvider($request);

        return response()->json(['state' => $this->manager->generateState()]);
    }

    public function callback(Request $request, string $provider): View|RedirectResponse|JsonResponse
    {
        if ($provider === 'naver' && $request->isMethod('GET')) {
            return view('social-auth::naver-callback', [
                'callbackUrl' => route('social-auth.callback', 'naver'),
                'clientId' => config('social-auth.providers.naver.client_id'),
            ]);
        }

        try {
            if ($this->isConnecting($provider)) {
                $account = $this->manager->connect($provider, $request->all(), Auth::user());
                Session::forget(config('social-auth.session.connecting_provider', 'social_auth.connecting_provider'));

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'connected',
                        'provider' => $provider,
                        'account_id' => $account->id,
                        'redirect' => config('social-auth.redirects.connect_success', '/profile'),
                    ]);
                }

                return redirect()
                    ->to(config('social-auth.redirects.connect_success', '/profile'))
                    ->with('success', ucfirst($provider).' account connected.');
            }

            $result = $this->manager->handleCallback($provider, $request->all());

            if ($request->expectsJson()) {
                return response()->json($result);
            }

            return redirect()->to($result['redirect']);
        } catch (SocialAuthException $e) {
            $connecting = $this->isConnecting($provider);
            if ($connecting) {
                Session::forget(config('social-auth.session.connecting_provider', 'social_auth.connecting_provider'));
            }

            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()
                ->to($connecting
                    ? config('social-auth.redirects.connect_success', '/profile')
                    : config('social-auth.redirects.failure', '/login'))
                ->with('error', $e->getMessage());
        }
    }

    private function rememberConnectingProvider(Request $request): void
    {
        $provider = $request->query('provider');

        if ($request->query('context') !== 'connect' || ! Auth::check() || ! is_string($provider) || $provider === '') {
            return;
        }

        Session::put(
            config('social-auth.session.connecting_provider', 'social_auth.connecting_provider'),
            $provider,
        );
    }

    private function isConnecting(string $provider): bool
    {
        return Auth::check()
            && Session::get(config('social-auth.session.connecting_provider', 'social_auth.connecting_provider')) === $provider;
    }
}

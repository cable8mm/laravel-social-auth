<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth;

use Cable8mm\LaravelSocialAuth\Events\SocialLoginFailed;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Cable8mm\LaravelSocialAuth\Support\ProviderLabel;
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

        return response()->json(['nonce' => $this->manager->generateNonce()]);
    }

    public function state(Request $request): JsonResponse
    {
        $this->manager->rememberIntendedUrl($request->query('redirect'));
        $state = $this->manager->generateState();
        $this->rememberConnectIntent($request, $state);

        return response()->json(['state' => $state]);
    }

    public function callback(Request $request, string $provider): View|RedirectResponse|JsonResponse
    {
        if ($provider === 'naver' && $request->isMethod('GET')) {
            return view('social-auth::naver-callback', [
                'callbackUrl' => route('social-auth.callback', 'naver'),
                'clientId' => config('social-auth.providers.naver.client_id'),
            ]);
        }

        // Decided before the provider consumes the state: only a callback carrying the state issued for a connect is a connect.
        $connecting = $this->isConnecting($request, $provider);

        try {
            if ($connecting) {
                $account = $this->manager->connect($provider, $request->all(), Auth::user());
                Session::forget($this->connectIntentKey());

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
                    ->with('success', __('social-auth::messages.connected', ['provider' => ProviderLabel::for($provider)]));
            }

            $result = $this->manager->handleCallback($provider, $request->all());

            if ($request->expectsJson()) {
                return response()->json($result);
            }

            return redirect()->to($result['redirect']);
        } catch (SocialAuthException $e) {
            if ($connecting) {
                Session::forget($this->connectIntentKey());
            } else {
                event(new SocialLoginFailed($provider, $e->failureReason(), $e));
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

    /**
     * Redirect-based providers come back to the callback, so a connect is recognised by the state issued for it.
     * Any new state replaces the intent, so a later login state can never be mistaken for a connect.
     */
    private function rememberConnectIntent(Request $request, string $state): void
    {
        $provider = $request->query('provider');

        if ($request->query('context') !== 'connect' || ! Auth::check() || ! is_string($provider) || $provider === '') {
            Session::forget($this->connectIntentKey());

            return;
        }

        Session::put($this->connectIntentKey(), ['provider' => $provider, 'state' => $state]);
    }

    private function isConnecting(Request $request, string $provider): bool
    {
        $intent = Session::get($this->connectIntentKey());
        $state = $request->input('state');

        return Auth::check()
            && is_array($intent)
            && ($intent['provider'] ?? null) === $provider
            && is_string($intent['state'] ?? null)
            && is_string($state)
            && hash_equals($intent['state'], $state);
    }

    private function connectIntentKey(): string
    {
        return config('social-auth.session.connect_intent', 'social_auth.connect_intent');
    }
}

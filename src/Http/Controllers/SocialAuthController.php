<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Http\Controllers;

use Cable8mm\LaravelSocialAuth\Contracts\RegistrationConsentContract;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialLoginManager $manager,
    ) {}

    /**
     * Return a fresh nonce for Google GIS (called before rendering the button).
     */
    public function nonce(): JsonResponse
    {
        $nonce = $this->manager->generateNonce();

        return response()->json(['nonce' => $nonce]);
    }

    /**
     * Return a fresh state for Kakao / Naver.
     */
    public function state(): JsonResponse
    {
        $state = $this->manager->generateState();

        return response()->json(['state' => $state]);
    }

    /**
     * Provider callback (POST from JS SDK).
     */
    public function callback(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        try {
            $payload = $request->all();
            $result = $this->manager->handleCallback($provider, $payload);

            if ($request->expectsJson()) {
                return response()->json($result);
            }

            return redirect()->to($result['redirect']);
        } catch (SocialAuthException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()
                ->to(config('social-auth.redirects.failure', '/login'))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show consent form for pending registration.
     */
    public function showConsent(): View|RedirectResponse
    {
        if (! $this->manager->hasPendingRegistration()) {
            return redirect()->to(config('social-auth.redirects.failure', '/login'));
        }

        $pending = $this->manager->getPendingRegistration();
        $terms = app(RegistrationConsentContract::class)->allTerms();

        return view('social-auth::consent', [
            'pending' => $pending,
            'terms' => $terms,
        ]);
    }

    /**
     * Process consent form.
     */
    public function submitConsent(Request $request): RedirectResponse|JsonResponse
    {
        $accepted = [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'consent_')) {
                $termKey = substr($key, 8);
                $accepted[$termKey] = (bool) $value;
            }
        }

        // Also accept bare keys
        $terms = app(RegistrationConsentContract::class)->allTerms();
        foreach (array_keys($terms) as $key) {
            if ($request->has($key)) {
                $accepted[$key] = (bool) $request->input($key);
            }
        }

        try {
            $result = $this->manager->completeRegistration($accepted);

            if ($request->expectsJson()) {
                return response()->json($result);
            }

            return redirect()->to($result['redirect']);
        } catch (SocialAuthException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Connect social account (authenticated).
     */
    public function connect(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if ($user === null) {
            throw SocialAuthException::unauthenticated();
        }

        try {
            $account = $this->manager->connect($provider, $request->all(), $user);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'connected',
                    'provider' => $provider,
                    'account_id' => $account->id,
                ]);
            }

            return redirect()
                ->to(config('social-auth.redirects.connect_success', '/profile'))
                ->with('success', ucfirst($provider).' account connected.');
        } catch (SocialAuthException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()
                ->to(config('social-auth.redirects.connect_success', '/profile'))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Disconnect social account (authenticated).
     */
    public function disconnect(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if ($user === null) {
            throw SocialAuthException::unauthenticated();
        }

        try {
            $this->manager->disconnect($provider, $user);

            if ($request->expectsJson()) {
                return response()->json(['status' => 'disconnected', 'provider' => $provider]);
            }

            return redirect()
                ->to(config('social-auth.redirects.disconnect_success', '/profile'))
                ->with('success', ucfirst($provider).' account disconnected.');
        } catch (SocialAuthException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return redirect()
                ->to(config('social-auth.redirects.disconnect_success', '/profile'))
                ->with('error', $e->getMessage());
        }
    }
}

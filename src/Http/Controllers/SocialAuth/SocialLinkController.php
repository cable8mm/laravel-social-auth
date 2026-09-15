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

class SocialLinkController extends Controller
{
    public function __construct(
        private readonly SocialLoginManager $manager,
    ) {}

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
                    'redirect' => config('social-auth.redirects.connect_success', '/profile'),
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

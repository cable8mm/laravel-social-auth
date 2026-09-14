<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth;

use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
            $result = $this->manager->handleCallback($provider, $request->all());

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
}

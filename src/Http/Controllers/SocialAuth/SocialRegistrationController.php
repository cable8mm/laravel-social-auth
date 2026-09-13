<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth;

use Cable8mm\LaravelSocialAuth\Contracts\RegistrationConsentContract;
use Cable8mm\LaravelSocialAuth\Exceptions\SocialAuthException;
use Cable8mm\LaravelSocialAuth\Services\SocialLoginManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SocialRegistrationController extends Controller
{
    public function __construct(
        private readonly SocialLoginManager $manager,
        private readonly RegistrationConsentContract $consent,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (! $this->manager->hasPendingRegistration()) {
            return redirect()->to(config('social-auth.redirects.failure', '/login'));
        }

        return view('social-auth::consent', [
            'pending' => $this->manager->getPendingRegistration(),
            'terms' => $this->consent->allTerms(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $accepted = [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'consent_')) {
                $accepted[substr($key, 8)] = (bool) $value;
            }
        }

        foreach (array_keys($this->consent->allTerms()) as $key) {
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
}

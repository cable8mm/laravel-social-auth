<?php

declare(strict_types=1);

use Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

$prefix = config('social-auth.routes.prefix', 'social-auth');
$middleware = config('social-auth.routes.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::get('nonce', [SocialAuthController::class, 'nonce'])->name('social-auth.nonce');
        Route::get('state', [SocialAuthController::class, 'state'])->name('social-auth.state');

        Route::post('{provider}/callback', [SocialAuthController::class, 'callback'])
            ->middleware('throttle:'.config('social-auth.rate_limit.callback', '10,1'))
            ->name('social-auth.callback');

        Route::get('consent', [SocialAuthController::class, 'showConsent'])->name('social-auth.consent');
        Route::post('consent', [SocialAuthController::class, 'submitConsent'])->name('social-auth.consent.submit');

        Route::middleware('auth')->group(function () {
            Route::post('{provider}/connect', [SocialAuthController::class, 'connect'])
                ->middleware('throttle:'.config('social-auth.rate_limit.connect', '10,1'))
                ->name('social-auth.connect');

            Route::delete('{provider}/disconnect', [SocialAuthController::class, 'disconnect'])
                ->name('social-auth.disconnect');
        });
    });

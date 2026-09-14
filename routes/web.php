<?php

declare(strict_types=1);

use Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth\SocialLinkController;
use Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth\SocialLoginController;
use Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth\SocialRegistrationController;
use Cable8mm\LaravelSocialAuth\Http\Controllers\SocialAuth\SocialWebhookController;
use Illuminate\Support\Facades\Route;

$prefix = config('social-auth.routes.prefix', 'social-auth');
$middleware = config('social-auth.routes.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::get('nonce', [SocialLoginController::class, 'nonce'])->name('social-auth.nonce');
        Route::get('state', [SocialLoginController::class, 'state'])->name('social-auth.state');

        Route::match(['get', 'post'], '{provider}/callback', [SocialLoginController::class, 'callback'])
            ->middleware('throttle:'.config('social-auth.rate_limit.callback', '10,1'))
            ->name('social-auth.callback');

        Route::get('consent', [SocialRegistrationController::class, 'create'])->name('social-auth.consent');
        Route::post('consent', [SocialRegistrationController::class, 'store'])->name('social-auth.consent.submit');

        Route::middleware('auth')->group(function () {
            Route::post('{provider}/connect', [SocialLinkController::class, 'connect'])
                ->middleware('throttle:'.config('social-auth.rate_limit.connect', '10,1'))
                ->name('social-auth.connect');

            Route::delete('{provider}/disconnect', [SocialLinkController::class, 'disconnect'])
                ->name('social-auth.disconnect');
        });
    });

Route::prefix($prefix)
    ->middleware(config('social-auth.routes.webhook_middleware', []))
    ->group(function () {
        Route::post('google/events', [SocialWebhookController::class, 'googleRisc'])
            ->name('social-auth.webhooks.google');
        Route::post('kakao/events', [SocialWebhookController::class, 'kakaoAccountStatus'])
            ->name('social-auth.webhooks.kakao');
        Route::post('naver/deauthorize', [SocialWebhookController::class, 'naverDisconnect'])
            ->name('social-auth.webhooks.naver');
    });

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

Route::get('/', function () {
    return view('workbench::home', ['user' => auth()->user()]);
});

Route::get('/test/social-auth/prepare-consent', function () {
    Session::put(config('social-auth.session.pending_registration'), [
        'provider' => 'google',
        'provider_id' => 'dusk-google-user',
        'email' => 'dusk@example.com',
        'email_verified' => true,
        'name' => 'Dusk User',
        'nickname' => null,
        'avatar' => null,
        'access_token' => null,
        'refresh_token' => null,
        'token_expires_at' => null,
        'raw' => [],
    ]);

    return redirect()->route('social-auth.consent');
});

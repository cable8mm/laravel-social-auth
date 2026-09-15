<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Workbench\App\Models\User;

Route::get('/', function () {
    return view('workbench::home', ['user' => auth()->user()]);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => '입력한 이메일 또는 비밀번호가 올바르지 않습니다.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/profile');
    });

    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');

    Route::post('/register', function (Request $request) {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/profile')->with('success', '회원가입이 완료되었습니다.');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', function (Request $request) {
        return view('profile', ['user' => $request->user()]);
    })->name('profile');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');
});

Route::get('/test/social-auth/prepare-consent', function () {
    Session::forget('url.intended');
    Session::put(config('social-auth.session.intended'), '/');
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

<?php

declare(strict_types=1);
use App\Models\User;
use Cable8mm\LaravelSocialAuth\Models\SocialAccount;

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => env('SOCIAL_AUTH_USER_MODEL', User::class),

    /*
    |--------------------------------------------------------------------------
    | Social Account Model
    |--------------------------------------------------------------------------
    */
    'social_account_model' => SocialAccount::class,

    /*
    |--------------------------------------------------------------------------
    | Social Accounts Table
    |--------------------------------------------------------------------------
    */
    'table' => env('SOCIAL_AUTH_TABLE', 'social_accounts'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'google' => [
            'enabled' => (bool) env('GOOGLE_CLIENT_ID'),
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'), // optional for GIS JWT flow
            'one_tap' => (bool) env('GOOGLE_ONE_TAP', false),
            'js_sdk_url' => 'https://accounts.google.com/gsi/client',
            'scopes' => ['openid', 'email', 'profile'],
            'name_mapping' => 'name', // maps provider name field → users.name
            'nickname_mapping' => null, // use nickname generator
        ],

        'kakao' => [
            'enabled' => (bool) env('KAKAO_JAVASCRIPT_KEY') && (bool) env('KAKAO_REST_API_KEY'),
            'client_id' => env('KAKAO_REST_API_KEY'),
            'js_client_id' => env('KAKAO_JAVASCRIPT_KEY'),
            'client_secret' => env('KAKAO_CLIENT_SECRET'),
            'redirect' => env('KAKAO_REDIRECT_URI'),
            'js_sdk_url' => 'https://t1.kakaocdn.net/kakao_js_sdk/2.7.2/kakao.min.js',
            'scopes' => ['profile_nickname', 'account_email'],
            'name_mapping' => null, // Kakao name is not auto-mapped
            'nickname_mapping' => null,
        ],

        'naver' => [
            'enabled' => (bool) env('NAVER_CLIENT_ID') && (bool) env('NAVER_CLIENT_SECRET'),
            'client_id' => env('NAVER_CLIENT_ID'),
            'client_secret' => env('NAVER_CLIENT_SECRET'),
            'redirect' => env('NAVER_REDIRECT_URI'),
            'js_sdk_url' => 'https://static.nid.naver.com/js/naveridlogin_js_sdk_2.0.2.js',
            'scopes' => [],
            'name_mapping' => 'name',
            'nickname_mapping' => 'nickname',
        ],

        'apple' => [
            'enabled' => (bool) env('APPLE_CLIENT_ID')
                && (bool) env('APPLE_TEAM_ID')
                && (bool) env('APPLE_KEY_ID')
                && (bool) env('APPLE_PRIVATE_KEY')
                && (bool) env('APPLE_REDIRECT_URI'),
            'client_id' => env('APPLE_CLIENT_ID'),
            'team_id' => env('APPLE_TEAM_ID'),
            'key_id' => env('APPLE_KEY_ID'),
            'private_key' => env('APPLE_PRIVATE_KEY'),
            'redirect' => env('APPLE_REDIRECT_URI'),
            'js_sdk_url' => 'https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js',
            'scopes' => ['name', 'email'],
            'name_mapping' => 'name',
            'nickname_mapping' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Button Order
    |--------------------------------------------------------------------------
    */
    'button_order' => ['naver', 'kakao', 'google', 'apple'],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'prefix' => 'social-auth',
        'middleware' => ['web'],
        'webhook_middleware' => [],
        'callback' => '{provider}/callback',
        'consent' => 'consent',
        'connect' => '{provider}/connect',
        'disconnect' => '{provider}/disconnect',
    ],

    'webhooks' => [
        'google' => [
            'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
        ],
        'kakao' => [
            'jwks_url' => 'https://kauth.kakao.com/.well-known/jwks.json',
        ],
        'apple' => [
            'jwks_url' => 'https://appleid.apple.com/auth/keys',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    */
    'redirects' => [
        'login_success' => '/',
        'registration_consent' => '/social-auth/consent',
        'connect_success' => env('SOCIAL_AUTH_CONNECT_REDIRECT', '/profile'),
        'disconnect_success' => env('SOCIAL_AUTH_DISCONNECT_REDIRECT', '/profile'),
        'failure' => env('SOCIAL_AUTH_FAILURE_REDIRECT', '/login'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Terms / Consent
    |--------------------------------------------------------------------------
    */
    'consent' => [
        'layout' => 'layouts.app',
        'required_terms' => [
            'terms_of_service' => [
                'label' => '이용약관',
                'url' => env('SOCIAL_AUTH_TERMS_URL', '/terms'),
                'required' => true,
            ],
            'privacy_policy' => [
                'label' => '개인정보 처리방침',
                'url' => env('SOCIAL_AUTH_PRIVACY_URL', '/privacy'),
                'required' => true,
            ],
        ],
        'optional_terms' => [
            'marketing' => [
                'label' => '마케팅 정보 수신 동의',
                'url' => env('SOCIAL_AUTH_MARKETING_URL', null),
                'required' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Verification Policy
    |--------------------------------------------------------------------------
    |
    | provider_email_verified (default): trust provider's verified flag
    | always_verified: set email_verified_at whenever email exists
    | never_verified: always leave email_verified_at null
    |
    */
    'email_verification_policy' => 'provider_email_verified',

    /*
    |--------------------------------------------------------------------------
    | Nickname Generator
    |--------------------------------------------------------------------------
    |
    | Can be:
    | - null (uses default: user_ + random)
    | - Closure (bound at runtime)
    | - class implementing NicknameGeneratorContract
    | - 'App\Models\User@generateNickname' style method
    |
    */
    'nickname_generator' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Storage
    |--------------------------------------------------------------------------
    */
    'store_tokens' => true,

    /*
    |--------------------------------------------------------------------------
    | Remote Revoke
    |--------------------------------------------------------------------------
    |
    | When true, disconnect will attempt to revoke the token at the provider.
    | Requires store_tokens = true. Invalid combination is blocked at boot.
    |
    */
    'remote_revoke' => true,

    /*
    |--------------------------------------------------------------------------
    | Protect Last Login Method
    |--------------------------------------------------------------------------
    */
    'protect_last_login_method' => true,

    /*
    |--------------------------------------------------------------------------
    | Session Keys
    |--------------------------------------------------------------------------
    */
    'session' => [
        'pending_registration' => 'social_auth.pending_registration',
        'nonce' => 'social_auth.nonce',
        'state' => 'social_auth.state',
        'intended' => 'social_auth.intended',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'callback' => '10,1', // 10 attempts per minute
        'connect' => '10,1',
    ],

];

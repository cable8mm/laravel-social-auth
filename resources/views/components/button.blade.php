@props([
    'provider' => '',
    'context' => 'login',
])

@once
    @include('social-auth::styles')
@endonce

@php
    $manager = app(\Cable8mm\LaravelSocialAuth\Services\SocialLoginManager::class);
    try {
        $p = $manager->provider($provider);
        if (!$p->isEnabled()) {
            return;
        }
        $clientId = $p->getJsClientId();
        $jsSdkUrl = $p->getJsSdkUrl();
    } catch (\Throwable $e) {
        return;
    }

    $labels = [
        'google' => 'Google',
        'kakao' => '카카오',
        'naver' => '네이버',
        'apple' => 'Apple',
    ];
    $label = $labels[$provider] ?? ucfirst($provider);

    $buttonClasses = match($provider) {
        'google' => 'social-btn social-btn-google',
        'kakao' => 'social-btn social-btn-kakao',
        'naver' => 'social-btn social-btn-naver',
        'apple' => 'social-btn social-btn-apple',
        default => 'social-btn',
    };
@endphp

<div
    class="{{ $buttonClasses }}"
    data-provider="{{ $provider }}"
    data-context="{{ $context }}"
    data-client-id="{{ $clientId }}"
    data-callback-url="{{ route('social-auth.callback', $provider) }}"
    data-connect-url="{{ $context === 'connect' ? route('social-auth.connect', $provider) : '' }}"
    data-nonce-url="{{ route('social-auth.nonce') }}"
    data-state-url="{{ route('social-auth.state') }}"
    data-intended-url="{{ url()->current() }}"
    data-redirect-url="{{ $provider === 'apple' ? ($p->getConfig()['redirect'] ?? '') : '' }}"
>
    @if($provider === 'google')
        {{-- Google GIS button is rendered by JS --}}
        <div id="google-btn-{{ $context }}" class="google-gis-button"></div>
        @once
            <script src="{{ $jsSdkUrl }}" async defer></script>
        @endonce
    @elseif($provider === 'kakao')
        <button type="button" class="btn-kakao" onclick="window.SocialAuth && window.SocialAuth.loginKakao('{{ $context }}')">
            <svg class="kakao-symbol" aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                <path d="M12 3C6.477 3 2 6.582 2 11c0 2.835 1.87 5.32 4.69 6.72L5.5 21l4.04-2.05c.79.16 1.61.25 2.46.25 5.523 0 10-3.582 10-8.2S17.523 3 12 3Z" />
            </svg>
            {{ match ($context) {
                'register' => '카카오로 시작하기',
                'connect' => '카카오 계정 연결',
                default => '카카오 로그인',
            } }}
        </button>
        <script src="{{ $jsSdkUrl }}"></script>
    @elseif($provider === 'naver')
        <button type="button" class="btn-naver" onclick="window.SocialAuth && window.SocialAuth.loginNaver('{{ $context }}')">
            <svg class="naver-symbol" aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                <path d="M4 4h5.5l5 7.1V4H20v16h-5.5l-5-7.1V20H4V4Z" />
            </svg>
            <span>{{ match ($context) {
                'register' => '네이버로 시작하기',
                'connect' => '네이버 계정 연결',
                default => '네이버 아이디로 로그인',
            } }}</span>
        </button>
        <script src="{{ $jsSdkUrl }}"></script>
    @elseif($provider === 'apple')
        <div
            id="appleid-signin"
            data-color="black"
            data-border="true"
            data-type="{{ $context === 'register' ? 'sign-up' : 'sign-in' }}"
            data-width="100%"
            data-height="48"
        ></div>
        <script src="{{ $jsSdkUrl }}"></script>
    @endif
</div>

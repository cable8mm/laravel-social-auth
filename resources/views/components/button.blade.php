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
    ];
    $label = $labels[$provider] ?? ucfirst($provider);

    $buttonClasses = match($provider) {
        'google' => 'social-btn social-btn-google',
        'kakao' => 'social-btn social-btn-kakao',
        'naver' => 'social-btn social-btn-naver',
        default => 'social-btn',
    };
@endphp

<div
    class="{{ $buttonClasses }}"
    @if($provider === 'naver') id="naverIdLogin" @endif
    data-provider="{{ $provider }}"
    data-context="{{ $context }}"
    data-client-id="{{ $clientId }}"
    data-callback-url="{{ route('social-auth.callback', $provider) }}"
    data-connect-url="{{ $context === 'connect' ? route('social-auth.connect', $provider) : '' }}"
    data-nonce-url="{{ route('social-auth.nonce') }}"
    data-state-url="{{ route('social-auth.state') }}"
>
    @if($provider === 'google')
        {{-- Google GIS button is rendered by JS --}}
        <div id="google-btn-{{ $context }}" class="google-gis-button"></div>
        @once
            <script src="{{ $jsSdkUrl }}" async defer></script>
        @endonce
    @elseif($provider === 'kakao')
        <button type="button" class="btn-kakao" onclick="window.SocialAuth && window.SocialAuth.loginKakao('{{ $context }}')">
            {{ $label }}로 {{ $context === 'connect' ? '연결' : '로그인' }}
        </button>
        <script src="{{ $jsSdkUrl }}"></script>
    @elseif($provider === 'naver')
        <div id="naver-btn-{{ $context }}" class="naver-login-button"></div>
        <script src="{{ $jsSdkUrl }}"></script>
    @endif
</div>

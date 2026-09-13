@props([
    'provider' => '',
    'context' => 'login',
])

@php
    $manager = app(\Cable8mm\LaravelSocialAuth\Services\SocialLoginManager::class);
    try {
        $p = $manager->provider($provider);
        if (!$p->isEnabled()) {
            return;
        }
        $clientId = $p->getClientId();
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
        <script src="{{ $jsSdkUrl }}" async defer></script>
    @elseif($provider === 'kakao')
        <button type="button" class="btn-kakao" onclick="window.SocialAuth && window.SocialAuth.loginKakao('{{ $context }}')">
            {{ $label }}로 {{ $context === 'connect' ? '연결' : '로그인' }}
        </button>
        <script src="{{ $jsSdkUrl }}" integrity="sha384-TiCUE00h649CAMonG18JNtUJLrldn5NReH7zE5mO4zq2e4b0s8v9x0y1z2a3b4c5" crossorigin="anonymous"></script>
    @elseif($provider === 'naver')
        <div id="naver-btn-{{ $context }}" class="naver-login-button"></div>
        <script src="{{ $jsSdkUrl }}"></script>
    @endif
</div>

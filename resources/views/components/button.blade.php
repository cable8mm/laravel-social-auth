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
        $clientId = $p->getJsClientId();
        $jsSdkUrl = $p->getJsSdkUrl();
    } catch (\Throwable $e) {
        return;
    }

    $buttonClasses = match($provider) {
        'google' => 'social-btn social-btn-google block min-h-12 w-full '.($context === 'connect' ? 'max-w-[12.5rem]' : 'max-w-[17.5rem]'),
        'kakao' => 'social-btn social-btn-kakao block min-h-12 w-full max-w-[17.5rem]',
        'naver' => 'social-btn social-btn-naver block min-h-12 w-full max-w-[17.5rem] overflow-hidden rounded-xl bg-[#03A94D]',
        'apple' => 'social-btn social-btn-apple block min-h-12 w-full max-w-[17.5rem]',
        'github' => 'social-btn social-btn-github block min-h-12 w-full max-w-[17.5rem]',
        default => 'social-btn block min-h-12 w-full max-w-[17.5rem]',
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
    data-google-sdk-url="{{ $provider === 'google' ? $jsSdkUrl : '' }}"
    data-google-button-width="{{ $provider === 'google' && $context === 'connect' ? '200' : '280' }}"
    data-google-button-type="standard"
    data-redirect-url="{{ in_array($provider, ['apple', 'github'], true) ? ($p->getConfig()['redirect'] ?? '') : '' }}"
    data-authorize-url="{{ $provider === 'github' ? ($p->getConfig()['authorize_url'] ?? '') : '' }}"
    data-scope="{{ $provider === 'github' ? implode(' ', $p->getConfig()['scopes'] ?? []) : '' }}"
>
    @if($provider === 'google')
        {{-- Google GIS button is rendered by JS --}}
        <div id="google-btn-{{ $context }}" class="google-gis-button min-h-12 w-full">
            <button
                type="button"
                class="google-gis-fallback inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-3 text-[0.9375rem] font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 focus-visible:outline-3 focus-visible:outline-zinc-500/35 focus-visible:outline-offset-2 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                onclick="window.SocialAuth && window.SocialAuth.initGoogle()"
            >
                {{ __('social-auth::views.buttons.google.'.(in_array($context, ['register', 'connect'], true) ? $context : 'login')) }}
            </button>
        </div>
        @once
            <script src="{{ $jsSdkUrl }}" async defer></script>
        @endonce
    @elseif($provider === 'kakao')
        <button type="button" class="btn-kakao inline-flex min-h-12 w-full items-center justify-center rounded-xl border-0 bg-[#FEE500] px-4 py-3 text-[0.9375rem] font-bold leading-tight text-black/85 transition duration-150 hover:-translate-y-px hover:bg-[#F5DC00] hover:shadow-lg focus-visible:outline-3 focus-visible:outline-[#191919]/35 focus-visible:outline-offset-2 active:translate-y-0" onclick="window.SocialAuth && window.SocialAuth.loginKakao('{{ $context }}')">
            <svg class="kakao-symbol mr-2.5 h-[1.125rem] w-[1.125rem] fill-current" aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                <path d="M12 3C6.477 3 2 6.582 2 11c0 2.835 1.87 5.32 4.69 6.72L5.5 21l4.04-2.05c.79.16 1.61.25 2.46.25 5.523 0 10-3.582 10-8.2S17.523 3 12 3Z" />
            </svg>
            {{ __('social-auth::views.buttons.kakao.'.(in_array($context, ['register', 'connect'], true) ? $context : 'login')) }}
        </button>
        <script src="{{ $jsSdkUrl }}"></script>
    @elseif($provider === 'naver')
        <button type="button" class="btn-naver inline-flex min-h-12 w-full items-center justify-center rounded-xl border-0 bg-[#03A94D] px-4 py-3 text-[0.9375rem] font-bold leading-tight text-white transition duration-150 hover:-translate-y-px hover:bg-[#029344] hover:shadow-lg focus-visible:outline-3 focus-visible:outline-[#03A94D]/35 focus-visible:outline-offset-2 active:translate-y-0" onclick="window.SocialAuth && window.SocialAuth.loginNaver('{{ $context }}')">
            <svg class="naver-symbol mr-2.5 h-6 w-6 fill-current" aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                <path d="M4 4h5.5l5 7.1V4H20v16h-5.5l-5-7.1V20H4V4Z" />
            </svg>
            <span>{{ __('social-auth::views.buttons.naver.'.(in_array($context, ['register', 'connect'], true) ? $context : 'login')) }}</span>
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
    @elseif($provider === 'github')
        <button type="button" class="btn-github inline-flex min-h-12 w-full items-center justify-center rounded-xl border-0 bg-[#24292F] px-4 py-3 text-[0.9375rem] font-bold leading-tight text-white transition duration-150 hover:-translate-y-px hover:bg-[#32383F] hover:shadow-lg focus-visible:outline-3 focus-visible:outline-[#24292F]/35 focus-visible:outline-offset-2 active:translate-y-0" onclick="window.SocialAuth && window.SocialAuth.loginGitHub('{{ $context }}')">
            <svg class="github-symbol mr-2.5 h-5 w-5 fill-current" aria-hidden="true" viewBox="0 0 16 16" focusable="false">
                <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8Z" />
            </svg>
            <span>{{ __('social-auth::views.buttons.github.'.(in_array($context, ['register', 'connect'], true) ? $context : 'login')) }}</span>
        </button>
    @endif
</div>

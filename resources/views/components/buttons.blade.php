@props([
    'context' => 'login', // login | register | connect
])

@php
    $manager = app(\Cable8mm\LaravelSocialAuth\Services\SocialLoginManager::class);
    $providers = $manager->enabledProviders();
@endphp

@if(count($providers) > 0)
<div {{ $attributes->merge(['class' => 'social-auth-buttons mx-auto flex w-full max-w-[17.5rem] flex-col gap-3']) }} data-social-auth-buttons>
    @foreach($providers as $provider)
        <x-social-auth::button
            :provider="$provider"
            :context="$context"
        />
    @endforeach

    <div
        data-social-auth-message
        class="hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm leading-5 text-red-700"
        role="alert"
        aria-live="assertive"
        tabindex="-1"
    ></div>
</div>
@endif

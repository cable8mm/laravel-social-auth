@props([
    'context' => 'login', // login | register | connect
])

@php
    $manager = app(\Cable8mm\LaravelSocialAuth\Services\SocialLoginManager::class);
    $providers = $manager->enabledProviders();
@endphp

@if(count($providers) > 0)
<div {{ $attributes->merge(['class' => 'social-auth-buttons']) }}>
    @foreach($providers as $provider)
        <x-social-auth::button
            :provider="$provider"
            :context="$context"
        />
    @endforeach
</div>
@endif

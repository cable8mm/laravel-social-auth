@php
    $google = config('social-auth.providers.google', []);
@endphp

@if(auth()->guest() && ($google['enabled'] ?? false) && ($google['one_tap'] ?? false))
    <div
        data-provider="google"
        data-context="one-tap"
        data-google-one-tap
        data-client-id="{{ $google['client_id'] }}"
        data-callback-url="{{ route('social-auth.callback', 'google') }}"
        data-nonce-url="{{ route('social-auth.nonce') }}"
    ></div>

    <script src="{{ $google['js_sdk_url'] }}" async defer></script>
    @include('social-auth::scripts')
@endif

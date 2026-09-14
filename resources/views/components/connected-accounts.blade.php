@props([])

@php
    $user = auth()->user();
    if (!$user) {
        return;
    }
    $service = app(\Cable8mm\LaravelSocialAuth\Services\SocialAccountService::class);
    $manager = app(\Cable8mm\LaravelSocialAuth\Services\SocialLoginManager::class);
    $accounts = $service->listForUser($user)->keyBy('provider');
    $enabled = $manager->enabledProviders();
@endphp

<div {{ $attributes->merge(['class' => 'social-connected-accounts']) }}>
    <h3>연결된 SNS 계정</h3>
    <ul>
        @foreach($enabled as $provider)
            <li data-provider="{{ $provider }}">
                <span class="provider-name">{{ ucfirst($provider) }}</span>
                @if($accounts->has($provider))
                    <span class="status connected">연결됨</span>
                    <form method="POST" action="{{ route('social-auth.disconnect', $provider) }}" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-disconnect">연결 해제</button>
                    </form>
                @else
                    <span class="status disconnected">미연결</span>
                    <x-social-auth::button :provider="$provider" context="connect" />
                @endif
            </li>
        @endforeach
    </ul>
</div>

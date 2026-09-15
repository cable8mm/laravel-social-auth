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
    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">연결된 SNS 계정</h3>
    <ul class="mt-3 divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
        @foreach($enabled as $provider)
            <li class="flex items-center gap-4 px-4 py-3" data-provider="{{ $provider }}">
                <div class="min-w-0 flex-1">
                    <span class="provider-name font-medium text-zinc-900 dark:text-zinc-100">{{ ucfirst($provider) }}</span>
                @if($accounts->has($provider))
                    <span class="status connected ml-2 text-sm text-emerald-600 dark:text-emerald-400">연결됨</span>
                @else
                    <span class="status disconnected ml-2 text-sm text-zinc-500 dark:text-zinc-400">미연결</span>
                @endif
                </div>
                @if($accounts->has($provider))
                    <form method="POST" action="{{ route('social-auth.disconnect', $provider) }}" class="shrink-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-disconnect inline-flex cursor-pointer items-center rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-semibold text-zinc-700 shadow-sm transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-red-500 focus-visible:outline-offset-2 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-red-700 dark:hover:bg-red-950 dark:hover:text-red-300">연결 해제</button>
                    </form>
                @else
                    <div class="flex shrink-0 justify-end">
                        <x-social-auth::button :provider="$provider" context="connect" />
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</div>

@extends(config('social-auth.consent.layout', 'layouts.app'))

@section('content')
<div class="social-auth-consent-page flex min-h-screen items-center justify-center bg-zinc-100 px-4 py-6 leading-normal text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <main class="social-auth-consent w-full max-w-[30rem] rounded-xl border border-zinc-200 bg-white p-6 shadow-xl shadow-zinc-900/5 sm:rounded-2xl sm:p-8 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/20">
        <p class="social-auth-consent__eyebrow mb-2 text-[0.8125rem] font-bold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('social-auth::views.consent.eyebrow', ['provider' => \Cable8mm\LaravelSocialAuth\Support\ProviderLabel::for((string) ($pending['provider'] ?? 'SNS'))]) }}</p>
        <h1 class="text-2xl leading-tight font-bold tracking-tight sm:text-3xl">{{ __('social-auth::views.consent.title') }}</h1>
        <p class="social-auth-consent__intro mt-3 mb-6 text-[0.9375rem] text-zinc-600 dark:text-zinc-400">{{ __('social-auth::views.consent.intro') }}</p>

        @if(session('error'))
            <div class="social-auth-consent__error mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300" role="alert">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('social-auth.consent.submit') }}" data-social-auth-consent-form>
            @csrf

            <div class="social-auth-consent__all mb-2 flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3.5 dark:border-zinc-800 dark:bg-zinc-800/50">
                <input id="social-auth-consent-all" name="consent_all" type="checkbox" class="size-[1.125rem] shrink-0 accent-zinc-900 dark:accent-zinc-100" data-social-auth-consent-all>
                <label for="social-auth-consent-all" class="flex-1 cursor-pointer text-[0.9375rem] font-bold">{{ __('social-auth::views.consent.select_all') }}</label>
            </div>

            <fieldset class="social-auth-consent__terms m-0 border-0 p-0">
                <legend class="sr-only">{{ __('social-auth::views.consent.legend') }}</legend>

                @foreach($terms as $key => $term)
                    <div class="social-auth-consent__term flex items-start gap-3 border-t border-zinc-100 py-4 dark:border-zinc-800">
                        <input
                            id="social-auth-term-{{ $key }}"
                            type="checkbox"
                            name="{{ $key }}"
                            value="1"
                            class="mt-0.5 size-[1.125rem] shrink-0 accent-zinc-900 dark:accent-zinc-100"
                            data-social-auth-consent
                            @if($term['required'] ?? false) required @endif
                        >
                        <label for="social-auth-term-{{ $key }}" class="flex-1 cursor-pointer text-[0.9375rem]">
                            {{ $term['label'] ?? (\Illuminate\Support\Facades\Lang::has('social-auth::views.consent.terms.'.$key) ? __('social-auth::views.consent.terms.'.$key) : $key) }}
                            <span class="social-auth-consent__badge ml-1 whitespace-nowrap text-[0.8125rem] text-zinc-500 dark:text-zinc-400">({{ ($term['required'] ?? false) ? __('social-auth::views.consent.required') : __('social-auth::views.consent.optional') }})</span>
                        </label>
                        @if(!empty($term['url']))
                            <a
                                class="social-auth-consent__original-link shrink-0 whitespace-nowrap text-[0.9375rem] font-semibold text-inherit underline decoration-1 underline-offset-[3px]"
                                href="{{ $term['url'] }}"
                                target="_blank"
                                rel="noopener"
                            >{{ __('social-auth::views.consent.view_original') }}</a>
                        @endif
                    </div>
                @endforeach
            </fieldset>

            <button class="social-auth-consent__submit mt-3 min-h-12 w-full cursor-pointer rounded-lg border-0 bg-zinc-900 px-4 py-3 text-[0.9375rem] font-bold text-white transition hover:bg-zinc-700 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-blue-500/45 active:translate-y-px dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300" type="submit">{{ __('social-auth::views.consent.submit') }}</button>
        </form>
    </main>
</div>
@endsection

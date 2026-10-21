@extends(config('social-auth.consent.layout', 'layouts.app'))

@section('content')
    <main class="social-auth-callback flex min-h-screen items-center justify-center p-6">
        <div class="social-auth-callback__card flex w-full max-w-md items-center gap-3.5 rounded-2xl border border-zinc-200 bg-white px-6 py-5 shadow-xl shadow-zinc-900/5 dark:border-zinc-700 dark:bg-zinc-900" id="social-auth-callback-card">
            <span class="social-auth-callback__spinner size-5 shrink-0 animate-spin rounded-full border-[0.2rem] border-zinc-200 border-t-zinc-900 dark:border-zinc-700 dark:border-t-zinc-100" id="social-auth-callback-spinner" aria-hidden="true"></span>
            <p class="social-auth-callback__status text-[0.9375rem]" id="social-auth-status" role="status" aria-live="polite">
                {{ __('social-auth::views.naver_callback.checking') }}
            </p>
        </div>
    </main>

    <script>
        (() => {
            const status = document.getElementById('social-auth-status');
            const card = document.getElementById('social-auth-callback-card');
            const hash = new URLSearchParams(window.location.hash.slice(1));
            const query = new URLSearchParams(window.location.search);
            const accessToken = hash.get('access_token');
            const error = hash.get('error') || query.get('error');

            if (error || !accessToken) {
                card.classList.add('is-error', 'text-rose-700', 'dark:text-rose-300');
                document.getElementById('social-auth-callback-spinner').classList.add('hidden');
                status.textContent = @json(__('social-auth::views.naver_callback.failed', ['provider' => \Cable8mm\LaravelSocialAuth\Support\ProviderLabel::for('naver')]));
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @json($callbackUrl);
            form.style.display = 'none';

            const fields = {
                _token: @json(csrf_token()),
                access_token: accessToken,
                refresh_token: hash.get('refresh_token') || '',
                expires_in: hash.get('expires_in') || '',
                state: hash.get('state') || query.get('state') || '',
            };

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search);
            form.submit();
        })();
    </script>
@endsection

@extends(config('social-auth.consent.layout', 'layouts.app'))

@section('content')
    <style>
        :root {
            color: #18181b;
            background: #f4f4f5;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; }

        .social-auth-callback {
            align-items: center;
            display: flex;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }

        .social-auth-callback__card {
            align-items: center;
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 1rem;
            box-shadow: 0 1.25rem 3rem rgb(24 24 27 / 8%);
            display: flex;
            gap: .875rem;
            max-width: 28rem;
            padding: 1.25rem 1.5rem;
            width: 100%;
        }

        .social-auth-callback__spinner {
            animation: social-auth-spin .8s linear infinite;
            border: .2rem solid #e4e4e7;
            border-radius: 999px;
            border-top-color: #18181b;
            flex: 0 0 auto;
            height: 1.25rem;
            width: 1.25rem;
        }

        .social-auth-callback__status { font-size: .9375rem; }
        .social-auth-callback__card.is-error { color: #be123c; }
        .social-auth-callback__card.is-error .social-auth-callback__spinner { display: none; }

        @keyframes social-auth-spin { to { transform: rotate(360deg); } }

        @media (prefers-color-scheme: dark) {
            :root { color: #f4f4f5; background: #111113; }
            .social-auth-callback__card { background: #1c1c1f; border-color: #3f3f46; }
            .social-auth-callback__spinner { border-color: #3f3f46; border-top-color: #f4f4f5; }
        }
    </style>
    <main class="social-auth-callback">
        <div class="social-auth-callback__card" id="social-auth-callback-card">
            <span class="social-auth-callback__spinner" aria-hidden="true"></span>
            <p class="social-auth-callback__status" id="social-auth-status" role="status" aria-live="polite">
                로그인 정보를 확인하고 있습니다.
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
                card.classList.add('is-error');
                status.textContent = query.get('error_description') || hash.get('error_description') || '네이버 로그인을 완료하지 못했습니다.';
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

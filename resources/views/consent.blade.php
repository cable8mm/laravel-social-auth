<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>약관 동의</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
            background: #f5f6f8;
            color: #17191c;
        }

        * { box-sizing: border-box; }

        body {
            align-items: center;
            display: flex;
            justify-content: center;
            margin: 0;
            min-height: 100vh;
            padding: 1.5rem 1rem;
        }

        .social-auth-consent {
            background: #fff;
            border: 1px solid #e3e5e8;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgb(17 24 39 / 8%);
            max-width: 30rem;
            padding: 2rem;
            width: 100%;
        }

        .social-auth-consent__eyebrow {
            color: #6b7280;
            font-size: .8125rem;
            font-weight: 700;
            letter-spacing: .04em;
            margin: 0 0 .5rem;
            text-transform: uppercase;
        }

        h1 {
            font-size: clamp(1.5rem, 5vw, 1.875rem);
            letter-spacing: -.03em;
            line-height: 1.2;
            margin: 0;
        }

        .social-auth-consent__intro {
            color: #59616d;
            font-size: .9375rem;
            margin: .75rem 0 1.5rem;
        }

        .social-auth-consent__error {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: .625rem;
            color: #be123c;
            font-size: .875rem;
            margin-bottom: 1rem;
            padding: .75rem .875rem;
        }

        .social-auth-consent__terms {
            border: 0;
            margin: 0;
            padding: 0;
        }

        .social-auth-consent__term {
            align-items: flex-start;
            border-top: 1px solid #edf0f2;
            display: flex;
            gap: .75rem;
            padding: 1rem 0;
        }

        .social-auth-consent__term input {
            accent-color: #111827;
            flex: 0 0 auto;
            height: 1.125rem;
            margin: .125rem 0 0;
            width: 1.125rem;
        }

        .social-auth-consent__term label {
            cursor: pointer;
            flex: 1;
            font-size: .9375rem;
        }

        .social-auth-consent__term a {
            color: inherit;
            font-weight: 650;
            text-decoration-thickness: 1px;
            text-underline-offset: 3px;
        }

        .social-auth-consent__badge {
            color: #6b7280;
            font-size: .8125rem;
            margin-left: .25rem;
            white-space: nowrap;
        }

        .social-auth-consent__submit {
            background: #111827;
            border: 0;
            border-radius: .625rem;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-size: .9375rem;
            font-weight: 700;
            margin-top: .75rem;
            min-height: 3rem;
            padding: .75rem 1rem;
            transition: background-color 150ms ease, transform 150ms ease;
            width: 100%;
        }

        .social-auth-consent__submit:hover { background: #374151; }
        .social-auth-consent__submit:active { transform: translateY(1px); }
        .social-auth-consent__submit:focus-visible { outline: 3px solid rgb(59 130 246 / 45%); outline-offset: 2px; }

        .sr-only {
            height: 1px;
            margin: -1px;
            overflow: hidden;
            padding: 0;
            position: absolute;
            width: 1px;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        @media (prefers-color-scheme: dark) {
            :root { background: #111315; color: #f3f4f6; }
            .social-auth-consent { background: #1b1e22; border-color: #30343a; box-shadow: 0 1rem 3rem rgb(0 0 0 / 22%); }
            .social-auth-consent__eyebrow, .social-auth-consent__intro, .social-auth-consent__badge { color: #a5acb7; }
            .social-auth-consent__error { background: #351b22; border-color: #71303d; color: #fda4af; }
            .social-auth-consent__term { border-color: #30343a; }
            .social-auth-consent__submit { background: #f3f4f6; color: #111827; }
            .social-auth-consent__submit:hover { background: #d1d5db; }
        }

        @media (max-width: 30rem) {
            .social-auth-consent { border-radius: .75rem; padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <main class="social-auth-consent">
        @php
            $providerLabels = [
                'google' => 'Google',
                'kakao' => '카카오',
                'naver' => '네이버',
            ];
            $providerLabel = $providerLabels[$pending['provider'] ?? ''] ?? ucfirst($pending['provider'] ?? 'SNS');
        @endphp

        <p class="social-auth-consent__eyebrow">{{ $providerLabel }} 계정으로 가입</p>
        <h1>약관에 동의해 주세요</h1>
        <p class="social-auth-consent__intro">서비스 이용을 시작하려면 아래 약관을 확인하고 동의해 주세요.</p>

        @if(session('error'))
            <div class="social-auth-consent__error" role="alert">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('social-auth.consent.submit') }}">
            @csrf

            <fieldset class="social-auth-consent__terms">
                <legend class="sr-only">서비스 약관 동의</legend>

                @foreach($terms as $key => $term)
                    <div class="social-auth-consent__term">
                        <input
                            id="social-auth-term-{{ $key }}"
                            type="checkbox"
                            name="{{ $key }}"
                            value="1"
                            @if($term['required'] ?? false) required @endif
                        >
                        <label for="social-auth-term-{{ $key }}">
                            @if(!empty($term['url']))
                                <a href="{{ $term['url'] }}" target="_blank" rel="noopener">{{ $term['label'] }}</a>
                            @else
                                {{ $term['label'] }}
                            @endif
                            <span class="social-auth-consent__badge">({{ ($term['required'] ?? false) ? '필수' : '선택' }})</span>
                        </label>
                    </div>
                @endforeach
            </fieldset>

            <button class="social-auth-consent__submit" type="submit">동의하고 가입 완료</button>
        </form>
    </main>
</body>
</html>

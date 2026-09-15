<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Social Auth Workbench' }}</title>
    @vite('resources/js/app.js')
    <style>
        :root {
            color-scheme: light dark;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #18181b;
            background: #f4f4f5;
        }

        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; }

        .workbench-shell {
            margin: 0 auto;
            max-width: 52rem;
            min-height: 100vh;
            padding: 3rem 1.5rem;
        }

        .workbench-header {
            align-items: flex-start;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .workbench-header__actions, .workbench-home-links { align-items: center; display: flex; gap: 1rem; }

        .workbench-kicker {
            color: #71717a;
            font-size: .75rem;
            font-weight: 750;
            letter-spacing: .08em;
            margin: 0 0 .5rem;
            text-transform: uppercase;
        }

        h1, h2 { letter-spacing: -.035em; margin: 0; }
        h1 { font-size: clamp(1.75rem, 5vw, 2.5rem); }
        h2 { font-size: clamp(1.35rem, 4vw, 1.75rem); }

        .workbench-status {
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 999px;
            color: #71717a;
            font-size: .8125rem;
            padding: .5rem .75rem;
            white-space: nowrap;
        }

        .workbench-status--signed-in { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }

        .workbench-card {
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 1rem;
            box-shadow: 0 1.25rem 3rem rgb(24 24 27 / 7%);
            padding: clamp(1.5rem, 5vw, 2.5rem);
        }

        .workbench-auth-shell { align-items: center; display: flex; justify-content: center; min-height: 100vh; padding: 2rem 1rem; }
        .workbench-auth-card { background: #fff; border: 1px solid #e4e4e7; border-radius: 1rem; box-shadow: 0 1.25rem 3rem rgb(24 24 27 / 7%); max-width: 30rem; padding: clamp(1.5rem, 5vw, 2.5rem); width: 100%; }
        .workbench-auth-card h1 { margin-bottom: .5rem; }
        .workbench-muted { color: #71717a; margin: .5rem 0 0; }
        .workbench-social-actions { margin-top: 1.75rem; }
        .workbench-form { display: grid; gap: 1rem; }
        .workbench-form label:not(.workbench-checkbox) { display: grid; gap: .4rem; }
        .workbench-form label > span { font-size: .875rem; font-weight: 650; }
        .workbench-form input[type="email"], .workbench-form input[type="password"], .workbench-form input[type="text"] { background: #fff; border: 1px solid #d4d4d8; border-radius: .65rem; color: #18181b; font: inherit; padding: .75rem .8rem; width: 100%; }
        .workbench-form input:focus { border-color: #2563eb; outline: 3px solid rgb(37 99 235 / 18%); }
        .workbench-checkbox { align-items: center; display: flex; gap: .5rem; font-size: .875rem; }
        .workbench-primary-button, .workbench-secondary-button { border: 0; border-radius: .65rem; cursor: pointer; font: inherit; font-weight: 700; padding: .75rem 1rem; }
        .workbench-primary-button { background: #18181b; color: #fff; width: 100%; }
        .workbench-secondary-button { background: #fff; border: 1px solid #d4d4d8; color: #3f3f46; }
        .workbench-primary-button:hover, .workbench-secondary-button:hover { opacity: .82; }
        .workbench-auth-footer { color: #71717a; font-size: .875rem; margin: 1.5rem 0 0; text-align: center; }
        .workbench-auth-footer a { color: #2563eb; font-weight: 700; text-underline-offset: 3px; }
        .workbench-primary-link { background: #18181b; border-radius: .65rem; color: #fff; display: inline-block; font-weight: 700; margin-top: 1.25rem; padding: .75rem 1rem; text-decoration: none; }
        .workbench-alert { border-radius: .65rem; font-size: .875rem; margin: 1rem 0; padding: .75rem 1rem; }
        .workbench-alert--error { background: #fef2f2; color: #b91c1c; }
        .workbench-alert--success { background: #f0fdf4; color: #166534; }
        .workbench-profile-summary { margin-bottom: 1.5rem; }
        .workbench-profile-summary h2 { margin-top: .25rem; }

        .workbench-card__intro { margin-bottom: 1.75rem; }
        .workbench-card__intro > p:last-child { color: #71717a; margin: .75rem 0 0; }
        .workbench-divider { align-items: center; color: #a1a1aa; display: flex; gap: .75rem; margin: 2rem 0 1rem; }
        .workbench-divider::before, .workbench-divider::after { background: #e4e4e7; content: ""; height: 1px; flex: 1; }
        .workbench-divider span { font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .workbench-link { color: #2563eb; font-size: .875rem; font-weight: 650; text-underline-offset: 3px; }

        @media (max-width: 40rem) {
            .workbench-shell { padding: 2rem 1rem; }
            .workbench-header { flex-direction: column; }
        }

        @media (prefers-color-scheme: dark) {
            :root { background: #111113; color: #f4f4f5; }
            .workbench-kicker, .workbench-card__intro > p:last-child { color: #a1a1aa; }
            .workbench-status, .workbench-card, .workbench-auth-card { background: #1c1c1f; border-color: #3f3f46; }
            .workbench-form input[type="email"], .workbench-form input[type="password"], .workbench-form input[type="text"], .workbench-secondary-button { background: #27272a; border-color: #52525b; color: #f4f4f5; }
            .workbench-status--signed-in { background: #052e16; border-color: #166534; color: #bbf7d0; }
            .workbench-card { box-shadow: 0 1.25rem 3rem rgb(0 0 0 / 25%); }
            .workbench-divider::before, .workbench-divider::after { background: #3f3f46; }
            .workbench-link { color: #93c5fd; }
            .workbench-primary-link, .workbench-primary-button { background: #f4f4f5; color: #18181b; }
        }
    </style>
</head>
<body>
    @yield('content')

    <x-social-auth::one-tap />
</body>
</html>

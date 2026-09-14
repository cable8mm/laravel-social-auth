<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Social Auth Workbench' }}</title>
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

        .workbench-card__intro { margin-bottom: 1.75rem; }
        .workbench-card__intro > p:last-child { color: #71717a; margin: .75rem 0 0; }
        .social-auth-buttons { margin-inline: 0; }

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
            .workbench-status, .workbench-card { background: #1c1c1f; border-color: #3f3f46; }
            .workbench-status--signed-in { background: #052e16; border-color: #166534; color: #bbf7d0; }
            .workbench-card { box-shadow: 0 1.25rem 3rem rgb(0 0 0 / 25%); }
            .workbench-divider::before, .workbench-divider::after { background: #3f3f46; }
            .workbench-link { color: #93c5fd; }
        }
    </style>
</head>
<body>
    @yield('content')

    @include('social-auth::scripts')
    <x-social-auth::one-tap />
</body>
</html>

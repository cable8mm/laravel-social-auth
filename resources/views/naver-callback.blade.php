<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>네이버 로그인 처리 중</title>
</head>
<body>
    <p id="social-auth-status">네이버 로그인 처리 중입니다.</p>

    <script>
        (() => {
            const status = document.getElementById('social-auth-status');
            const hash = new URLSearchParams(window.location.hash.slice(1));
            const query = new URLSearchParams(window.location.search);
            const accessToken = hash.get('access_token');
            const error = hash.get('error') || query.get('error');

            if (error || !accessToken) {
                status.textContent = query.get('error_description') || hash.get('error_description') || '네이버 로그인을 완료하지 못했습니다.';
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @json($callbackUrl);
            form.style.display = 'none';

            const fields = {
                _token: document.querySelector('meta[name="csrf-token"]').content,
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
            status.textContent = '네이버 로그인 결과를 확인하고 있습니다.';
            form.submit();
        })();
    </script>
</body>
</html>

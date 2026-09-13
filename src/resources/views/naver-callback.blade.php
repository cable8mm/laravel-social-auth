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

            const payload = {
                access_token: accessToken,
                refresh_token: hash.get('refresh_token'),
                expires_in: hash.get('expires_in'),
                state: hash.get('state') || query.get('state'),
            };

            fetch(@json($callbackUrl), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            })
                .then(response => response.json())
                .then(data => {
                    if (data.redirect) {
                        window.location.replace(data.redirect);
                        return;
                    }

                    status.textContent = data.error || '네이버 로그인을 완료하지 못했습니다.';
                })
                .catch(() => {
                    status.textContent = '네이버 로그인 처리 중 오류가 발생했습니다.';
                });
        })();
    </script>
</body>
</html>

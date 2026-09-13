<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Social Auth Workbench</title>
</head>
<body>
    <main>
        <h1>Social Auth Workbench</h1>

        @if($user)
            <p data-testid="authenticated-user">로그인됨: {{ $user->email }}</p>
        @else
            <p data-testid="guest-user">로그인되지 않음</p>
        @endif

        <section aria-label="SNS 로그인 버튼">
            <x-social-auth::buttons context="login" />
        </section>

        @include('social-auth::scripts')
    </main>
</body>
</html>

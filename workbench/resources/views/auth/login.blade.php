@extends('layouts.app')

@section('content')
    <div class="workbench-auth-shell">
        <div class="workbench-auth-card">
            <p class="workbench-kicker">Welcome back</p>
            <h1>로그인</h1>
            <p class="workbench-muted">소셜 계정 또는 이메일로 로그인하세요.</p>

            @if ($errors->any())
                <div class="workbench-alert workbench-alert--error">{{ $errors->first() }}</div>
            @endif

            @if (session('error'))
                <div class="workbench-alert workbench-alert--error">{{ session('error') }}</div>
            @endif

            <div class="workbench-social-actions">
                <x-social-auth::buttons context="login" />
            </div>

            <div class="workbench-divider"><span>이메일로 로그인</span></div>

            <form method="POST" action="{{ url('/login') }}" class="workbench-form">
                @csrf
                <label>
                    <span>이메일</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                </label>
                <label>
                    <span>비밀번호</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <label class="workbench-checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span>로그인 상태 유지</span>
                </label>
                <button class="workbench-primary-button" type="submit">로그인</button>
            </form>

            <p class="workbench-auth-footer">계정이 없나요? <a href="{{ url('/register') }}">회원가입</a></p>
        </div>
    </div>
@endsection

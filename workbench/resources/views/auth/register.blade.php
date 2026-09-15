@extends('layouts.app')

@section('content')
    <div class="workbench-auth-shell">
        <div class="workbench-auth-card">
            <p class="workbench-kicker">Create your account</p>
            <h1>회원가입</h1>
            <p class="workbench-muted">소셜 계정 또는 이메일로 계정을 만드세요.</p>

            @if ($errors->any())
                <div class="workbench-alert workbench-alert--error">{{ $errors->first() }}</div>
            @endif

            <div class="workbench-social-actions">
                <x-social-auth::buttons context="register" />
            </div>

            <div class="workbench-divider"><span>이메일로 가입</span></div>

            <form method="POST" action="{{ url('/register') }}" class="workbench-form">
                @csrf
                <label>
                    <span>이름</span>
                    <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
                </label>
                <label>
                    <span>이메일</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>
                <label>
                    <span>비밀번호</span>
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>
                <label>
                    <span>비밀번호 확인</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
                <button class="workbench-primary-button" type="submit">회원가입</button>
            </form>

            <p class="workbench-auth-footer">이미 계정이 있나요? <a href="{{ url('/login') }}">로그인</a></p>
        </div>
    </div>
@endsection

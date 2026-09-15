@extends('layouts.app')

@section('content')
    <div class="workbench-shell">
        <header class="workbench-header">
            <div>
                <p class="workbench-kicker">Account</p>
                <h1>프로필</h1>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="workbench-secondary-button" type="submit">로그아웃</button>
            </form>
        </header>

        <main class="workbench-card">
            @if (session('success'))
                <div class="workbench-alert workbench-alert--success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="workbench-alert workbench-alert--error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="workbench-alert workbench-alert--error">{{ $errors->first() }}</div>
            @endif

            <section class="workbench-profile-summary">
                <p class="workbench-kicker">Signed in</p>
                <h2>{{ $user->name }}</h2>
                <p class="workbench-muted">{{ $user->email }}</p>
            </section>

            <div class="workbench-divider"><span>연결된 SNS 계정</span></div>

            <x-social-auth::connected-accounts />

            <div class="workbench-divider"><span>이메일 및 비밀번호</span></div>

            <form method="POST" action="{{ route('profile.credentials.update') }}" class="workbench-form">
                @csrf
                @method('PATCH')
                <label>
                    <span>이메일</span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email">
                </label>
                <p class="workbench-field-help">소셜 로그인만 사용하는 계정은 이메일이 비어 있을 수 있습니다.</p>
                <label>
                    <span>새 비밀번호</span>
                    <input type="password" name="password" autocomplete="new-password" minlength="8">
                </label>
                <label>
                    <span>새 비밀번호 확인</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8">
                </label>
                <p class="workbench-field-help">비밀번호를 변경하지 않으려면 비워 두세요.</p>
                <button class="workbench-primary-button" type="submit">이메일 및 비밀번호 저장</button>
            </form>
        </main>
    </div>
@endsection

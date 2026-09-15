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

            <section class="workbench-profile-summary">
                <p class="workbench-kicker">Signed in</p>
                <h2>{{ $user->name }}</h2>
                <p class="workbench-muted">{{ $user->email }}</p>
            </section>

            <div class="workbench-divider"><span>연결된 SNS 계정</span></div>

            <x-social-auth::connected-accounts />
        </main>
    </div>
@endsection

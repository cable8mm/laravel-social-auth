@extends('layouts.app')

@section('content')
    <div class="workbench-shell">
        <header class="workbench-header">
            <div>
                <p class="workbench-kicker">Cable8mm package workbench</p>
                <h1>Social Auth Workbench</h1>
            </div>

            @if($user)
                <div class="workbench-header__actions">
                    <span class="workbench-status workbench-status--signed-in" data-testid="authenticated-user">
                        로그인됨: {{ $user->email }}
                    </span>
                    <a class="workbench-link" href="{{ route('profile') }}">프로필</a>
                </div>
            @else
                <div class="workbench-header__actions">
                    <span class="workbench-status" data-testid="guest-user">로그인되지 않음</span>
                    <a class="workbench-link" href="{{ route('login') }}">로그인</a>
                </div>
            @endif
        </header>

        <main class="workbench-card">
            <div class="workbench-card__intro">
                <p class="workbench-kicker">Authentication preview</p>
                <h2>로그인 방법을 선택하세요</h2>
                <p>Google, Kakao, Naver, Apple 소셜 로그인 흐름을 Workbench에서 확인할 수 있습니다.</p>
            </div>

            @guest
                <section aria-label="SNS 로그인 버튼">
                    <x-social-auth::buttons context="login" />
                </section>
            @else
                <p class="workbench-muted">현재 로그인된 계정의 SNS 연결은 프로필 화면에서 관리할 수 있습니다.</p>
                <a class="workbench-primary-link" href="{{ route('profile') }}">프로필에서 계정 관리</a>
            @endguest

            <div class="workbench-divider"><span>개발자 테스트</span></div>

            @guest
                <div class="workbench-home-links">
                    <a class="workbench-link" href="{{ route('register') }}">회원가입 화면 열기</a>
                    <a class="workbench-link" href="{{ url('/test/social-auth/prepare-consent') }}">약관 동의 화면 열기</a>
                </div>
            @else
                <a class="workbench-link" href="{{ url('/test/social-auth/prepare-consent') }}">약관 동의 화면 열기</a>
            @endguest
        </main>
    </div>
@endsection

@extends('layouts.app')

@section('content')
    <div class="workbench-shell">
        <header class="workbench-header">
            <div>
                <p class="workbench-kicker">Cable8mm package workbench</p>
                <h1>Social Auth Workbench</h1>
            </div>

            @if($user)
                <span class="workbench-status workbench-status--signed-in" data-testid="authenticated-user">
                    로그인됨: {{ $user->email }}
                </span>
            @else
                <span class="workbench-status" data-testid="guest-user">로그인되지 않음</span>
            @endif
        </header>

        <main class="workbench-card">
            <div class="workbench-card__intro">
                <p class="workbench-kicker">Authentication preview</p>
                <h2>로그인 방법을 선택하세요</h2>
                <p>Google, Kakao, Naver, Apple 소셜 로그인 흐름을 Workbench에서 확인할 수 있습니다.</p>
            </div>

            <section aria-label="SNS 로그인 버튼">
                <x-social-auth::buttons context="login" />
            </section>

            <div class="workbench-divider"><span>개발자 테스트</span></div>

            <a class="workbench-link" href="{{ url('/test/social-auth/prepare-consent') }}">
                약관 동의 화면 열기
            </a>
        </main>
    </div>
@endsection

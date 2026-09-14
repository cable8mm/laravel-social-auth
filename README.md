# cable8mm/laravel-social-auth

[![code-style](https://github.com/cable8mm/laravel-social-auth/actions/workflows/code-style.yml/badge.svg)](https://github.com/cable8mm/laravel-social-auth/actions/workflows/code-style.yml)
[![run-tests](https://github.com/cable8mm/laravel-social-auth/actions/workflows/run-tests.yml/badge.svg)](https://github.com/cable8mm/laravel-social-auth/actions/workflows/run-tests.yml)
![PHP Version](https://img.shields.io/packagist/dependency-v/cable8mm/laravel-social-auth/php)
![Packagist Version](https://img.shields.io/packagist/v/cable8mm/laravel-social-auth)
![Packagist Downloads](https://img.shields.io/packagist/dt/cable8mm/laravel-social-auth)
![Packagist License](https://img.shields.io/packagist/l/cable8mm/laravel-social-auth)

Laravel SNS 인증 패키지 (Google GIS · Kakao JS SDK · Naver JS SDK · Sign in with Apple).  
**Socialite를 사용하지 않습니다.** 서버에서 credential/token을 직접 검증합니다.

![workbench](docs/assets/workbench.png)

## 왜 이 패키지를 사용하는가

Laravel에서 SNS 로그인을 구현할 때 보통 `laravel/socialite`를 사용합니다. Socialite는 서버 중심의 OAuth redirect 흐름을 일관된 방식으로 제공하지만, provider가 제공하는 모바일·브라우저 전용 로그인 UX를 그대로 활용하기에는 한계가 있습니다.

예를 들어 카카오나 네이버의 공식 JavaScript SDK를 사용하면 모바일에서 다음과 같은 흐름을 사용할 수 있습니다.

- 카카오톡이나 네이버 앱이 설치되어 있으면 앱 인증으로 전환
- 앱이 없거나 전환할 수 없으면 웹 로그인으로 fallback
- 사용자가 카카오 계정의 아이디나 비밀번호를 직접 입력하지 않아도 인증 가능

서버 redirect 중심의 로그인에서는 이런 앱 전환이 provider의 공식 JS SDK만큼 자연스럽게 동작하지 않을 수 있습니다. 특히 모바일 사용자는 카카오 계정의 로그인 아이디 자체를 모르는 경우도 많습니다.

Google도 마찬가지로 일반 OAuth redirect 대신 Google Identity Services(GIS)의 버튼과 One Tap을 사용할 수 있습니다. Google 계정 선택 UI를 현재 화면에 표시하고, 로그인 상태에 따라 One Tap을 시도하려면 브라우저에서 GIS를 직접 실행해야 합니다.

이 패키지는 다음 구조를 사용합니다.

```text
브라우저 공식 JS SDK
        ↓ credential / authorization code
Laravel 서버 검증 및 provider REST API 교환
        ↓
Laravel session 로그인
```

즉, 사용성은 provider의 공식 JS SDK에 맡기고, 인증 결과 검증과 사용자·SNS 계정 연결은 Laravel 서버에서 처리합니다. 그 대신 provider마다 SDK와 응답 형식이 다르므로 Socialite보다 설정과 구현이 provider별로 구체적이라는 trade-off가 있습니다.

## 요구사항

- PHP 8.3+
- Laravel 12 or Laravel 13
- SNS 가입을 사용하는 경우 `users.email` / `users.password` nullable 필요

## 설치

```bash
composer require cable8mm/laravel-social-auth
```

Laravel 패키지 자동 발견이 활성화되어 있으면 Service Provider가 자동 등록됩니다.

수동 등록이 필요하면 `config/app.php`:

```php
'providers' => [
    Cable8mm\LaravelSocialAuth\SocialAuthServiceProvider::class,
],
```

### 설치 순서

#### 1. 설치 명령 실행

설정 파일, 브라우저용 JS asset, SNS 가입에 필요한 `users.email` / `users.password` nullable 마이그레이션을 한 번에 publish합니다.

```bash
php artisan social-auth:install
```

이 명령은 마이그레이션을 실행하지 않습니다. provider 키를 `.env`에 설정한 뒤 다음 단계에서 직접 실행합니다.

#### 2. provider 키 설정

Google, Kakao, Naver 개발자 콘솔에서 발급받은 키를 `.env`에 설정합니다. 자세한 항목은 [`.env 예시`](#env-예시)를 참고하세요.

#### 3. 마이그레이션 실행

```bash
php artisan migrate
```

패키지의 `social_accounts` 마이그레이션은 Service Provider가 자동으로 로드합니다. 따라서 별도로 `social-auth-migrations`를 publish하지 않아도 됩니다.

#### 설정 파일만 수동으로 publish하는 경우

```bash
php artisan vendor:publish --tag=social-auth-config
```

#### 브라우저 JS asset만 수동으로 publish하는 경우

`social-auth:install`을 사용하지 않는다면 다음 명령으로 JS asset을 애플리케이션의 `resources/js/vendor/social-auth.js`에 publish합니다.

```bash
php artisan vendor:publish --tag=social-auth-assets
```

그 다음 기존 `resources/js/app.js`에 한 줄을 추가합니다.

```js
import './vendor/social-auth';
```

#### users 컬럼 마이그레이션만 수동으로 publish하는 경우

SNS 가입은 provider가 이메일을 제공하지 않거나 비밀번호를 사용하지 않는 경우를 지원하므로 `users.email`과 `users.password`가 nullable이어야 합니다. 이 단계는 필수입니다.

```bash
php artisan vendor:publish --tag=social-auth-user-columns
```

#### 4. 기본 뷰를 수정할 경우에만 views publish

기본 뷰를 그대로 사용하는 경우 이 단계는 건너뛰어도 됩니다.

```bash
php artisan vendor:publish --tag=social-auth-views
```

### users 컬럼 마이그레이션을 적용하지 않는 경우

애플리케이션의 `users` 테이블에서 이미 다음 조건을 만족한다면 `social-auth-user-columns` publish를 건너뛸 수 있습니다.

- `users.email`이 nullable
- `users.password`가 nullable

```php
$table->string('email')->nullable()->unique();
$table->string('password')->nullable();
$table->string('nickname')->nullable(); // 선택
```

패키지는 애플리케이션의 `users` 마이그레이션을 자동으로 변경하지 않습니다.

## .env 예시

```env
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_ONE_TAP=false

KAKAO_JAVASCRIPT_KEY=your-kakao-javascript-key
KAKAO_REST_API_KEY=your-kakao-rest-api-key
KAKAO_CLIENT_SECRET=your-kakao-client-secret
KAKAO_REDIRECT_URI=http://localhost:8000/social-auth/kakao/callback

NAVER_CLIENT_ID=your-naver-client-id
NAVER_CLIENT_SECRET=your-naver-client-secret
NAVER_REDIRECT_URI=http://localhost:8000/social-auth/naver/callback

APPLE_CLIENT_ID=your-apple-services-id
APPLE_TEAM_ID=your-apple-team-id
APPLE_KEY_ID=your-apple-key-id
APPLE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nyour-key\n-----END PRIVATE KEY-----"
APPLE_REDIRECT_URI=http://localhost:8000/social-auth/apple/callback

```

토큰 저장, provider 원격 revoke, 마지막 로그인 수단 보호, provider 이메일 검증 정책은 패키지의 보안 정책으로 항상 활성화됩니다. 별도의 `SOCIAL_AUTH_*` 환경 변수를 추가할 필요가 없습니다.

## Provider 콘솔 설정

### Google (Identity Services)

1. Google Cloud Console → OAuth 2.0 클라이언트 ID (웹)
2. 승인된 JavaScript 원본에 앱 도메인 추가
3. GIS 버튼 렌더링 전 `/social-auth/nonce` 에서 nonce를 받아 세션 저장 후 JWT `nonce` claim 비교 (replay 방지)

### Kakao

1. Kakao Developers 앱 등록, Web 도메인 / Redirect URI 등록
2. JavaScript 키와 REST API 키를 각각 `KAKAO_JAVASCRIPT_KEY`, `KAKAO_REST_API_KEY`에 설정
3. `http://localhost:8000`을 JavaScript SDK 도메인으로 등록
4. `http://localhost:8000/social-auth/kakao/callback`을 JavaScript 키와 REST API 키의 redirect URI로 등록
5. 동의 항목: 닉네임, 이메일(선택)

### Naver

1. Naver Developers 애플리케이션 등록
2. Callback URL 등록, Client ID / Secret 설정

### Apple

1. Apple Developer에서 Sign in with Apple을 활성화한 App ID와 웹용 Services ID를 등록합니다.
2. Services ID에 `APPLE_REDIRECT_URI`를 등록하고 Team ID, Key ID, private key를 준비합니다.
3. `.env`에 Apple 설정을 추가합니다. private key는 줄바꿈을 `\\n`으로 표현할 수 있습니다.
4. Apple 버튼은 Sign in with Apple JS로 인증을 시작하고, Laravel 서버가 authorization code와 identity token을 검증합니다.

## 사용법

### 공통 layout 설정

Provider SDK와 One Tap 초기화 코드는 애플리케이션의 Vite entry에 한 번만 import합니다. `php artisan social-auth:install` 실행 후 `resources/js/app.js`에 다음 한 줄을 추가하세요.

```js
import './vendor/social-auth';
```

기존 layout의 `@vite(['resources/css/app.css', 'resources/js/app.js'])`는 그대로 사용합니다. 별도의 `@vite` entry를 추가할 필요가 없습니다.

One Tap 컴포넌트는 공통 layout에 한 번 추가합니다. 일반적인 Laravel 앱의 `resources/views/layouts/app.blade.php`라면 content 영역 아래에 다음처럼 배치합니다.

```blade
<body>
    @yield('content')

    <x-social-auth::one-tap />
</body>
```

`@yield('content')` 대신 `{{ $slot }}`을 사용하는 컴포넌트 layout이라면 `$slot` 아래에 One Tap 컴포넌트를 추가하세요.

### 로그인 / 회원가입 버튼

로그인 또는 회원가입 화면에서 버튼이 필요한 위치에만 버튼 컴포넌트를 추가합니다. JS asset은 `app.js`에서 이미 import했으므로 화면마다 별도의 script를 추가하지 않습니다.

```blade
<x-social-auth::buttons context="login" />
```

회원가입 화면에서는 context만 변경합니다.

```blade
<x-social-auth::buttons context="register" />
```

#### Laravel 기본 로그인 화면에 추가하는 예시

Laravel이 생성한 `resources/views/pages/auth/login.blade.php` 또는 프로젝트의 로그인 view에서 Passkey 영역과 이메일 로그인 폼 사이에 다음 코드를 넣으면 됩니다.

```blade
<x-social-auth::buttons context="login" />

{{-- <x-passkey-verify /> --}}

<div class="flex items-center gap-4 text-xs font-bold uppercase tracking-widest text-zinc-400">
    <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span>
    <span>{{ __('이메일로 로그인') }}</span>
    <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span>
</div>
```

이 코드는 소셜 로그인 버튼과 기존 이메일 로그인 폼을 시각적으로 구분합니다. `x-passkey-verify`를 사용하는 애플리케이션이라면 주석을 제거하고, 사용하지 않는다면 그대로 두거나 삭제하면 됩니다.

### Google One Tap

Google One Tap을 로그인하지 않은 사용자의 모든 화면에서 시도하려면 `.env`에서 활성화하고 공통 layout에 컴포넌트를 한 번 추가합니다.

```env
GOOGLE_ONE_TAP=true
```

```blade
<x-social-auth::one-tap />
```

위 컴포넌트는 공통 layout에 한 번만 추가합니다. 로그인하지 않은 사용자이고 `GOOGLE_ONE_TAP=true`일 때만 One Tap 표시를 시도합니다.

One Tap은 로그인된 사용자에게는 렌더링되지 않습니다. Google 계정 세션, 브라우저 설정, 이전에 닫은 기록, 도메인 보안 조건에 따라 Google이 프롬프트를 표시하지 않을 수 있습니다. 기존 Google 로그인 버튼은 계속 fallback으로 사용할 수 있습니다.

### 약관 동의

신규 SNS 사용자는 pending 세션 저장 후 `/social-auth/consent` 로 이동합니다.  
필수 약관 URL은 `config/social-auth.php` 의 `consent` 섹션에서 설정합니다.

기본 동의 화면은 애플리케이션의 `layouts.app` 레이아웃을 사용합니다. 다른 레이아웃을 사용하는 경우 `config/social-auth.php`의 `consent.layout` 값을 변경하세요. 패키지 화면을 직접 수정하려면 `social-auth-views` 태그로 views를 publish할 수 있습니다.

### 프로필 연결/해제

```blade
@auth
    <x-social-auth::connected-accounts />
@endauth
```

### 이벤트

- `SocialUserRegistered`
- `SocialUserLoggedIn`
- `SocialAccountConnected`
- `SocialAccountDisconnected`

### Facade

```php
use Cable8mm\LaravelSocialAuth\Facades\SocialAuth;

SocialAuth::enabledProviders();
SocialAuth::generateNonce();
```

## 계정 정책 요약

| 정책                    | 기본 동작                       |
| ----------------------- | ------------------------------- |
| 자동 이메일 병합        | 금지                            |
| SNS 가입                | pending → 약관 동의 후 생성     |
| password                | 항상 null                       |
| email_verified_at       | provider 검증 플래그 따름       |
| Kakao name              | users.name 자동 매핑 안 함      |
| 마지막 로그인 수단 해제 | 기본 거부                       |
| remote revoke           | 기본 활성화 (provider별 지원 범위 내에서 시도) |

## 보안

- Google: JWKS 서명 + iss/aud/exp/sub + nonce
- Kakao/Naver: CSRF state + 서버 token/profile 검증
- 토큰 로그 금지, raw 민감 필드 제거
- session regenerate, rate limit
- 토큰은 암호화되어 저장되며, 연결 해제 시 provider 원격 revoke를 시도한 뒤 로컬 연결을 삭제

## 테스트

```bash
composer install
vendor/bin/phpunit
```

### 패키지 개발자 로컬 환경

Workbench에서 실제 Provider 로그인이나 브라우저 테스트를 실행하려면 로컬 환경 파일을 만듭니다. `.env`는 secret을 포함하므로 Git에 커밋하지 않고, 예시 파일을 복사해서 사용합니다. Workbench도 Laravel 앱과 같은 Vite asset 흐름을 사용합니다.

```bash
cp .env.example .env
```

`.env`에 Google, Kakao, Naver 개발자 콘솔에서 발급받은 값을 입력합니다. 모든 Provider는 `http://localhost:8000`을 기준으로 동작하도록 예시가 작성되어 있습니다.

개발자 콘솔에는 다음 주소를 등록해야 합니다.

- Google: Authorized JavaScript origin `http://localhost:8000`
- Kakao: JavaScript SDK domain `http://localhost:8000`
- Kakao: JavaScript key와 REST API key 양쪽에 `http://localhost:8000/social-auth/kakao/callback` 등록
- Naver: Callback URL `http://localhost:8000/social-auth/naver/callback`

키를 입력한 뒤 Workbench 서버를 실행합니다.

```bash
composer serve
```

브라우저에서 `http://localhost:8000`을 열어 실제 Provider 로그인을 확인할 수 있습니다. Provider 키가 없는 경우 해당 Provider 버튼은 표시되지 않습니다.

### Workbench + Laravel Dusk

패키지의 브라우저 테스트는 Testbench Workbench 애플리케이션을 대상으로 실행합니다. 기본 `composer test`에는 ChromeDriver가 필요한 브라우저 테스트를 포함하지 않습니다.

처음 한 번 ChromeDriver를 설치합니다.

```bash
vendor/bin/testbench-dusk dusk:chrome-driver --detect
```

그 다음 Workbench 데이터베이스를 새로 만들고 브라우저 테스트를 실행합니다.

```bash
composer test:browser
```

브라우저 테스트는 다음을 검증합니다.

- 실제 Workbench HTTP 서버에서 SNS 버튼이 Naver → Kakao → Google → Apple 순서로 렌더링되는지
- 세션에 저장된 pending social registration이 약관 동의 화면을 거쳐 가입 완료되는지
- 가입 완료 후 세션 인증과 redirect가 유지되는지

실제 provider 계정 인증은 외부 provider 경계에 의존하므로 이 테스트에 포함하지 않습니다. 실제 provider 검증은 별도의 opt-in live E2E 환경에서 수행해야 합니다.

## 실제 Provider 로그인

개발 환경에서 **실제 Google/Kakao/Naver/Apple 계정 E2E 로그인은 수행하지 않았습니다.**
단위·기능 테스트는 HTTP fake 및 JWT 자체 서명으로 검증합니다.  
배포 전 콘솔 키로 실제 연동 확인을 권장합니다.

## License

MIT

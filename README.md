# cable8mm/laravel-social-auth

[![code-style](https://github.com/cable8mm/laravel-social-auth/actions/workflows/code-style.yml/badge.svg)](https://github.com/cable8mm/laravel-social-auth/actions/workflows/code-style.yml)
[![run-tests](https://github.com/cable8mm/laravel-social-auth/actions/workflows/run-tests.yml/badge.svg)](https://github.com/cable8mm/laravel-social-auth/actions/workflows/run-tests.yml)
![PHP Version](https://img.shields.io/packagist/dependency-v/cable8mm/laravel-social-auth/php)
![Packagist Version](https://img.shields.io/packagist/v/cable8mm/laravel-social-auth)
![Packagist Downloads](https://img.shields.io/packagist/dt/cable8mm/laravel-social-auth)
![Packagist License](https://img.shields.io/packagist/l/cable8mm/laravel-social-auth)

Laravel SNS 인증 패키지 (Google GIS · Kakao JS SDK · Naver JS SDK).  
**Socialite를 사용하지 않습니다.** 서버에서 credential/token을 직접 검증합니다.

## 요구사항

- PHP 8.3+ / 8.4+
- Laravel 12
- `users.email` / `users.password` nullable 허용

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

### 설정 파일 / 뷰 / 마이그레이션 publish

```bash
php artisan vendor:publish --tag=social-auth-config
php artisan vendor:publish --tag=social-auth-views
php artisan vendor:publish --tag=social-auth-migrations
php artisan migrate
```

### users 테이블 요구사항

패키지는 `users` 마이그레이션을 강제로 변경하지 않습니다. 애플리케이션에서 다음을 허용해야 합니다.

```php
$table->string('email')->nullable()->unique();
$table->string('password')->nullable();
$table->string('nickname')->nullable(); // 선택
```

## .env 예시

```env
GOOGLE_AUTH_ENABLED=true
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_REDIRECT_URI=https://your-app.test/social-auth/google/callback

KAKAO_AUTH_ENABLED=true
KAKAO_CLIENT_ID=your-kakao-rest-api-key
KAKAO_CLIENT_SECRET=your-kakao-client-secret
KAKAO_REDIRECT_URI=https://your-app.test/social-auth/kakao/callback

NAVER_AUTH_ENABLED=true
NAVER_CLIENT_ID=your-naver-client-id
NAVER_CLIENT_SECRET=your-naver-client-secret
NAVER_REDIRECT_URI=https://your-app.test/social-auth/naver/callback

SOCIAL_AUTH_STORE_TOKENS=true
SOCIAL_AUTH_REMOTE_REVOKE=false
SOCIAL_AUTH_PROTECT_LAST_LOGIN=true
SOCIAL_AUTH_EMAIL_POLICY=provider_email_verified
SOCIAL_AUTH_LOGIN_REDIRECT=/
SOCIAL_AUTH_CONSENT_REDIRECT=/social-auth/consent
```

> **설정 검증**: `store_tokens=false` 이고 `remote_revoke=true` 이면 애플리케이션 부팅 시  
> `SocialAuthException` 이 발생하여 즉시 실패합니다.

## Provider 콘솔 설정

### Google (Identity Services)

1. Google Cloud Console → OAuth 2.0 클라이언트 ID (웹)
2. 승인된 JavaScript 원본에 앱 도메인 추가
3. GIS 버튼 렌더링 전 `/social-auth/nonce` 에서 nonce를 받아 세션 저장 후 JWT `nonce` claim 비교 (replay 방지)

### Kakao

1. Kakao Developers 앱 등록, Web 도메인 / Redirect URI 등록
2. REST API 키 = `KAKAO_CLIENT_ID`
3. 동의 항목: 닉네임, 이메일(선택)

### Naver

1. Naver Developers 애플리케이션 등록
2. Callback URL 등록, Client ID / Secret 설정

## 사용법

### 로그인 / 회원가입 버튼

```blade
<x-social-auth::buttons context="login" />
@include('social-auth::scripts')
```

### 약관 동의

신규 SNS 사용자는 pending 세션 저장 후 `/social-auth/consent` 로 이동합니다.  
필수 약관 URL은 `config/social-auth.php` 의 `consent` 섹션에서 설정합니다.

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
| remote revoke           | 기본 비활성 (store_tokens 필요) |

## 보안

- Google: JWKS 서명 + iss/aud/exp/sub + nonce
- Kakao/Naver: CSRF state + 서버 token/profile 검증
- 토큰 로그 금지, raw 민감 필드 제거
- session regenerate, rate limit
- store_tokens=false + remote_revoke=true → 부팅 차단

## 테스트

```bash
composer install
vendor/bin/phpunit
```

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

- 실제 Workbench HTTP 서버에서 SNS 버튼이 Naver → Kakao → Google 순서로 렌더링되는지
- 세션에 저장된 pending social registration이 약관 동의 화면을 거쳐 가입 완료되는지
- 가입 완료 후 세션 인증과 redirect가 유지되는지

실제 Google/Kakao/Naver 계정 인증은 외부 provider 경계에 의존하므로 이 테스트에 포함하지 않습니다. 실제 provider 검증은 별도의 opt-in live E2E 환경에서 수행해야 합니다.

## 실제 Provider 로그인

개발 환경에서 **실제 Google/Kakao/Naver 계정 E2E 로그인은 수행하지 않았습니다.**  
단위·기능 테스트는 HTTP fake 및 JWT 자체 서명으로 검증합니다.  
배포 전 콘솔 키로 실제 연동 확인을 권장합니다.

## License

MIT

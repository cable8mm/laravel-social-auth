# PRODUCT_SPEC.md — cable8mm/laravel-social-auth

## 목표

Google, Kakao, Naver, Apple, GitHub 로그인을 하나의 Laravel 패키지에서 통합 관리한다. 다른 Laravel 프로젝트에서 재사용 가능해야 한다.

## 지원 인증 방식

### Google

- Google Identity Services JS 버튼 사용
- 선택적으로 Google One Tap을 공통 layout에서 시도할 수 있음. 비로그인 사용자에게만 표시하고, 성공 시 기존 Google credential 로그인 흐름을 사용
- Google의 verified email이 있으면 로컬 이메일 인증 완료로 처리
- Google RISC 이벤트를 검증하고 `SocialAccountStatusChanged` 이벤트로 전달함. 세션 종료와 계정 보호 조치는 애플리케이션 정책으로 남김

### Kakao

- Kakao JS SDK 사용, 모바일에서 카카오톡 앱 설치 시 앱 인증 우선, 그 외 웹 로그인 fallback
- 이메일이 제공되지 않으면 로컬 email은 null
- 이메일이 제공되고 Kakao의 `kakao_account.is_email_verified`와 `kakao_account.is_email_valid`가 모두 true인 경우에만 로컬 이메일 인증 완료로 처리
- Kakao 닉네임을 자동으로 users.name에 넣지 않음. 로컬 nickname은 패키지 설정의 nickname generator 사용
- Kakao 계정 상태 변경 웹훅을 검증하고, 토큰 철회 이벤트에서는 로컬 토큰을 제거하며, 계정 탈퇴 이벤트에서는 SNS 연결만 제거함. 로컬 사용자 삭제와 전체 세션 종료는 애플리케이션 정책으로 남김

### Naver

- Naver JavaScript SDK 사용, 가능한 모바일 환경에서 네이버 앱 인증 시도, 그 외 웹 로그인 fallback
- 이메일이 응답에 있으면 로컬 email 인증 완료로 처리, 없으면 로컬 email은 null
- 이름과 닉네임 매핑은 설정 가능해야 함
- Naver 연결 끊기 Callback을 검증하고 외부 연결 해제 시 로컬 Naver SNS 연결만 제거함. 로컬 사용자 삭제는 애플리케이션 정책으로 남김
- 연결 해제 시 저장된 access token으로 Naver 토큰 삭제(remote revoke)를 시도함

### Apple

- Sign in with Apple JS를 사용해 웹에서 Apple Account 인증을 시작
- 서버에서 authorization code를 Apple REST API로 교환하고 identity token을 검증
- Apple의 `sub`를 provider ID로 사용하며, private relay 이메일을 그대로 지원
- 최초 인증에서만 전달될 수 있는 이름 정보는 가입 시 저장
- state와 nonce를 검증하고, 연결 해제 시 저장된 access token으로 Apple revoke를 시도
- Apple Server-to-Server Notification을 검증하고 `SocialAccountStatusChanged` 이벤트로 전달함
- `consent-revoked`와 `account-deleted` 이벤트에서는 로컬 Apple SNS 연결만 제거하고, 로컬 사용자 삭제와 전체 세션 종료는 애플리케이션 정책으로 남김

### GitHub

- GitHub App의 사용자 인증(웹 authorization code 흐름)을 사용 (GitHub는 공식 JS SDK가 없으므로 브라우저를 GitHub 인가 화면으로 redirect). OAuth App은 지원 대상이 아님
- GitHub App에는 Account permissions의 "Email addresses" 읽기 권한이 필요함
- 서버에서 authorization code를 access token으로 교환하고 state를 검증
- GitHub의 숫자 `id`를 provider ID로 사용 (`login`은 변경 가능하므로 provider ID로 쓰지 않음)
- 이메일 목록을 조회하고, `primary`이면서 `verified`인 이메일만 로컬 email로 사용하며 이 경우 로컬 이메일 인증 완료로 처리. 그런 이메일이 없으면 로컬 email은 null
- GitHub `name`은 설정으로 users.name에 매핑 가능. 값이 없으면 null
- GitHub App의 user access token은 만료됨(8시간, refresh token 6개월). 로그인/연결 시 access token, refresh token, 만료 시각을 저장
- 연결 해제 시 GitHub grant 삭제(remote revoke)를 시도함. grant 삭제에는 유효한 access token이 필요하므로, access token이 만료됐으면 refresh token으로 먼저 갱신한 뒤 삭제함. refresh token까지 만료되었거나 갱신·삭제가 실패해도 로컬 연결 해제는 진행

## 핵심 계정 정책

### SNS 회원가입

- SNS 인증 후 바로 사용자를 생성하지 않음. pending social registration을 세션에 저장
- 필수 이용약관 + 개인정보 처리방침 동의 화면을 거쳐야 가입 완료. 마케팅 동의는 선택
- 가입이 완료되면 이용약관, 개인정보 처리방침, 마케팅 정보 수신 동의 시각을 `users` 레코드의 `*_accepted_at` 컬럼에 저장함. 동의하지 않은 항목은 `null`로 둠
- 약관 문서의 실제 내용과 URL은 애플리케이션이 설정
- Naver Login Plus를 선택한 애플리케이션은 provider가 수집한 약관 동의 내역을 검증한 뒤 패키지의 별도 약관 화면 없이 가입을 완료할 수 있음
- provider-managed consent를 사용하는 경우 필수 약관 동의 확인에 실패하면 가입을 허용하지 않음

### 이메일 처리

- provider 이메일이 없으면 users.email은 null (임시 placeholder 생성 안 함)
- provider가 이메일을 검증됨으로 보고한 경우에만 email_verified_at = now(), 아니면 null
- 별도의 이메일 인증 메일을 반드시 요구하지 않음
- 기본 정책은 provider_email_verified. 정책은 설정으로 변경 가능해야 함

### 기존 계정 자동 병합 금지

- SNS 이메일과 기존 users.email이 같다는 이유만으로 자동 연결하지 않음
- 이메일 주소만으로 기존 계정에 SNS 계정을 연결하지 않음
- 기존 계정과 SNS 계정 연결은 반드시 로그인한 사용자가 프로필에서 명시적으로 실행
- provider ID가 기존 연결과 일치하지 않고 provider 이메일이 기존 사용자와 일치하면 약관 동의 화면으로 보내지 않고 로그인 화면으로 돌아가 안전한 안내 메시지를 표시함

### 로그인 실패 이벤트

- SNS 로그인/가입 callback이 실패하면 `SocialLoginFailed` 이벤트를 정확히 한 번 dispatch함. 애플리케이션은 이를 접속 기록 등에 사용할 수 있음
- 이벤트는 provider, 기계가 읽을 수 있는 고정 reason, 원인 예외를 전달함
  - `existing_email_unlinked`: 같은 이메일의 기존 계정이 있어 자동 병합을 거부함
  - `invalid_credential`: provider credential, authorization code, state, nonce 검증 실패
  - `consent_rejected`: provider-managed consent의 필수 약관 확인 실패
  - `provider_error`: provider API 서버 오류 등 그 밖의 실패
- 프로필의 SNS 계정 연결 흐름의 실패는 이 이벤트를 보내지 않음
- provider가 오류 응답(error, error_code, error_description 등)을 돌려준 경우 원인 예외에 그 정보를 담아 전달함. 액세스 토큰, 인가 코드, client_secret 같은 비밀값은 제거하며, 사용자에게 보이는 오류 문구는 일반적인 문구를 유지함
- 이벤트 추가로 기존 응답 코드, 리디렉트 위치, 오류 메시지는 바뀌지 않음

### SNS 계정 연결 (프로필)

- 인증된 사용자가 프로필에서 Google/Kakao/Naver/Apple/GitHub 계정을 연결할 수 있어야 함
- 이미 다른 사용자에 연결된 provider/provider_id는 연결 거부
- 같은 사용자에게 같은 provider 중복 연결 금지
- 연결 성공/실패 각각 사용자에게 안전하게 피드백

### SNS 계정 연결 해제

- 프로필에서 연결 해제 지원
- 마지막 로그인 수단(비밀번호 없음 + SNS 계정 1개)은 해제 거부 가능하도록 설정 가능. 이메일만으로는 로그인할 수 없으므로 이메일 유무와 관계없이 거부함
- 로컬 연결 해제와 provider의 remote revoke는 구분되는 별개 동작

### 이름과 닉네임

- provider별 이름 매핑은 설정 가능 (Google/Naver/GitHub/Apple은 name 매핑 가능, Kakao는 name 매핑 안 함)
- provider가 이름을 주지 않으면 users.name은 null. 기본 이름을 채우는 것은 애플리케이션 몫이므로 패키지는 users.name이 nullable이어야 함
- 닉네임 사용 여부는 provider가 주는 값의 품질에 따라 provider별로 설정 가능. 기본값은 Naver만 provider 닉네임을 사용하고, Kakao처럼 값이 부정확한 provider는 사용하지 않음
- provider 닉네임을 사용하지 않거나 값이 비어 있으면 nickname generator가 생성

## 다국어 메시지

- 사용자에게 보이는 모든 패키지 메시지(예외 메시지, 연결/해제 성공 flash)와 기본 Blade UI 문구(SNS 버튼, 약관 동의 화면, 연결된 SNS 계정 목록, Naver callback 화면)는 애플리케이션 locale에 따라 번역됨
- 패키지는 영어(`en`)와 한국어(`ko`) 번역을 제공하며, 애플리케이션은 `social-auth-lang` 태그로 번역 파일을 publish해 key 단위로 덮어쓸 수 있음
- provider 이름도 번역하며(예: kakao → Kakao / 카카오), 문장마다 provider를 하드코딩하지 않고 `:provider` 매개변수로 넣음. 단, SNS 버튼 문구는 provider별 브랜드 표기(예: `네이버 아이디로 로그인`)를 따르므로 provider·화면(login/register/connect)별 key로 둠
- 사용자에게 보이는 메시지에는 provider의 기술적 실패 원인을 넣지 않음. 원인은 예외에 별도로 보관해 이벤트와 로그에서 사용할 수 있게 함
- 웹훅 endpoint의 기계용 응답 코드는 번역 대상이 아님

## 인증 UI 요구사항

- 로그인 화면용 / 회원가입 화면용 SNS 버튼
- SNS 약관 동의 화면
- 프로필의 연결된 SNS 계정 목록 + 연결/연결해제 버튼
- provider가 설정되지 않은 경우 해당 버튼 숨김
- 버튼 순서 설정 가능, 기본 순서: Naver -> Kakao -> Google -> GitHub -> Apple
- Blade 기본 UI 제공, 애플리케이션에서 view publish 또는 override 가능해야 함

## Out of scope (이번 버전)

- Google 계정에 대한 remote revoke (access token을 저장하지 않는 credential 플로우이므로 미지원)
- Socialite 및 Socialite 기반 provider 확장
- GitHub 측 승인 취소 수신 (`github_app_authorization` 웹훅. 공개 HTTPS endpoint가 필요하며 이번 버전에서는 구현하지 않음)

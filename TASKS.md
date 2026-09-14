# TASKS.md — cable8mm/laravel-social-auth

Validation note: the local PHPUnit, Dusk, Pint, Composer checks, and manual live-provider verification have been completed.

## Done

- [x] Package skeleton, `composer.json`, `phpunit.xml.dist`, `pint.json`
- [x] `config/social-auth.php`
- [x] Contracts, Support classes (`SocialUser`, `PendingSocialRegistration`, `ChallengeGenerator`, `ConfigurationValidator`, `ProviderProfileMapper`, default nickname generator, default consent)
- [x] Exceptions
- [x] `SocialAccount` model, `SocialAccountRepository`, events
- [x] Verifiers: Google (JWKS + nonce), Kakao (code exchange + profile), Naver (profile + resultcode check)
- [x] Providers: Google, Kakao, Naver
- [x] `SocialLoginManager`
- [x] `SocialAuthServiceProvider` (bindings, boot-time config validation, publishing, route/view/migration loading)
- [x] HTTP controllers split by login, registration, and account-linking responsibilities + `routes/web.php`
- [x] `social_accounts` migration + publishable users-nullable-columns stub migration
- [x] Blade partials: buttons, consent, connected-accounts
- [x] Unit tests: `GoogleCredentialVerifierTest`, `KakaoTokenVerifierTest`, `NaverTokenVerifierTest`, `DefaultNicknameGeneratorTest`, `SocialAccountRepositoryTest`, `ConfigurationValidatorTest`
- [x] Feature tests: `GoogleLoginTest`, `KakaoLoginTest`, `NaverLoginTest`, `RegistrationConsentTest`
- [x] Feature tests: explicit account linking (success + conflict-with-another-user)
- [x] Feature test: account unlinking remote revoke failure handling
- [x] Feature test: callback without provider payload (cancel case)
- [x] Confirm the Kakao email-verification fields against the current official API documentation
- [x] Feature tests: provider button visibility and configured order
- [x] README installation and package requirements
- [x] Composer dependency installation with Packagist access
- [x] `vendor/bin/pint` format check
- [x] `vendor/bin/phpunit` full suite
- [x] Testbench Workbench + Laravel Dusk browser harness for button order and consent completion
- [x] Laravel 12 and 13 dependency constraints and CI coverage
- [x] Publishable migration for nullable user email and password columns
- [x] Register real apps in the Google / Kakao / Naver developer consoles, set redirect URIs, fill `.env`
- [x] Manual live-boundary login verification for Google, Kakao, and Naver
- [x] `social-auth:install` command for config and nullable user-columns migration publishing
- [x] Optional global Google One Tap component for guest users
- [x] Move browser integration to a publishable JS asset imported by the host Vite app entry
- [x] Provider remote revoke adapters for Kakao and Naver

## Apple Login

- [ ] Apple Developer Service ID, private key, redirect URI, and live login verification

## Remaining

- None

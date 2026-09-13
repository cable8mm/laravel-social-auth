# TASKS.md — cable8mm/laravel-social-auth

Sandbox note: this codebase was written in an environment with no PHP/Composer runtime and no network access to Packagist, so nothing below marked "run X" has actually been executed yet. Everything else was written directly against PRODUCT_SPEC.md / ARCHITECTURE.md / FRAMEWORK.md.

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
- [x] HTTP controllers + `routes/web.php`
- [x] `social_accounts` migration + publishable users-nullable-columns stub migration
- [x] Blade partials: buttons, consent, connected-accounts
- [x] Unit tests: `GoogleCredentialVerifierTest`, `KakaoTokenVerifierTest`, `NaverTokenVerifierTest`, `DefaultNicknameGeneratorTest`, `SocialAccountRepositoryTest`, `ConfigurationValidatorTest`
- [x] Feature tests: `GoogleLoginTest`, `KakaoLoginTest`, `NaverLoginTest`, `RegistrationConsentTest`
- [x] Testbench Workbench + Laravel Dusk browser harness for button order and consent completion
- [x] Laravel 12 and 13 dependency constraints and CI coverage

## Remaining

- [ ] Feature tests: explicit account linking (success + conflict-with-another-user)
- [ ] Feature tests: account unlinking (success, last-login-method protection, remote revoke failure handling)
- [ ] Feature tests: button visibility (provider disabled -> button hidden) and button order (Naver, Kakao, Google)
- [ ] Feature test: callback "cancel" case (provider returns to callback with no code/credential at all)
- [ ] `README.md`
- [ ] `composer install` in a real environment with Packagist access (not available in this sandbox)
- [ ] `vendor/bin/pint` — format check, not yet run
- [ ] `vendor/bin/phpunit` — full suite, not yet run or confirmed green
- [ ] Confirm the Kakao email-verification field against a live API response or current official docs (clears the ARCHITECTURE.md UNVERIFIED mark)
- [ ] Register real apps in the Google / Kakao / Naver developer consoles, set redirect URIs, fill `.env`
- [ ] E2E / live-boundary login test against each real provider (AGENTS.md TASK_EXECUTION step 8 — required because this package's core logic touches an EXTERNAL_BOUNDARY on every provider)

## Explicitly not started

- Naver remote revoke implementation (adapter boundary exists, `revoke()` currently returns `false`)

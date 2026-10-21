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
- [x] Kakao account status webhook implementation: SET verification, token/connection cleanup, Laravel event, and README guidance
- [x] Naver disconnect callback: HMAC/AES verification, local connection cleanup, and README guidance
- [x] Google RISC receiver: SET verification, Laravel event dispatch, and README guidance
- [x] Apple Server-to-Server Notification receiver: JWS verification, local connection cleanup, Laravel event dispatch, and README guidance

## Apple Login

- [ ] Apple Developer Service ID, private key, redirect URI, and live login verification

## GitHub Login

- [x] PRODUCT_SPEC / ARCHITECTURE / TECH_STACK updated for GitHub (requirements confirmed by the project owner; GitHub App chosen over OAuth App)
- [x] `GitHubTokenVerifier` + `GitHubProvider`: state check, code exchange, user + verified primary email, refresh, grant-delete revoke
- [x] `ProviderContract::revoke(SocialAccount)` aligned with ARCHITECTURE so GitHub can refresh an expired token before revoking
- [x] GitHub `raw` limited to seven fields so pending registration fits a cookie session
- [x] Config, button (redirect to GitHub authorize), translations, default button order
- [x] Unit/feature tests against GitHub's documented response shapes
- [x] Live verification with a registered GitHub App on the Workbench (2026-10-22): sign-up with consent, re-login, connect from profile, disconnect with a valid token, disconnect with an expired token (refresh then grant delete, `remote_revoked: true`), and disconnect after the user revoked on GitHub (refresh fails, local row removed, `remote_revoked: false`)
- [x] Verify the default intended redirect after social sign-up (live sign-up bounced `/social-auth/consent` -> `/login` -> `/` because the guest One Tap on the consent screen overwrote the intended URL; the consent URL is no longer stored as the destination; live re-check on the Workbench with One Tap on (2026-10-22): consent submit -> `/login` -> `/`, no return to `/social-auth/consent`)

## Remaining

- [ ] Verify the Kakao account status webhook against the live Kakao webhook test tool using a public HTTPS endpoint
- [ ] Verify the Naver disconnect callback against Naver's live callback delivery using a public HTTPS endpoint
- [ ] Register and verify Google RISC delivery against a public HTTPS endpoint using the Google RISC verification stream
- [ ] Register and verify Apple Server-to-Server Notification delivery against a public HTTPS endpoint
- [ ] Live-verify Kakao disconnect on the Workbench after the unlink request moved to `Authorization: Bearer` (expect `remote_revoked: true`)

## Code review (2026-10-22)

Findings from a read-only review of `main` at 073d752. Each fix task includes the test that would have caught it.

### Fix

- [x] Require the Google nonce: `GoogleProvider::verify` passes `null` when the session has no nonce and `GoogleCredentialVerifier::verify` then skips the check, so a leaked credential can be replayed from a session that never called `/nonce`. Reject a missing session nonce with `invalidNonce()`; add a callback test without a session nonce (`src/Providers/GoogleProvider.php:27`, `src/Verifiers/GoogleCredentialVerifier.php:48`)
- [x] Require the Apple nonce the same way; `sub` comes from the browser id_token, so a valid state plus any code plus another user's id_token passes today. Add Apple callback tests for missing/mismatched nonce (`src/Providers/AppleProvider.php:33`, `src/Verifiers/AppleTokenVerifier.php:86`)
- [x] Send the Kakao unlink access token as `Authorization: Bearer` (Kakao REST docs), not as a form field; remote revoke always fails today. Add a success test with `Http::assertSent` on the header; add a request-shape test for Naver revoke too (`src/Verifiers/KakaoTokenVerifier.php:100`)
- [x] Translate consent term labels: `consent.required_terms.*.label` / `optional_terms.*.label` are Korean strings in config and rendered raw, so `en` apps show Korean. Use `social-auth::views` keys, config label as an optional override (`config/social-auth.php:178-190`, `resources/views/consent.blade.php:208`)
- [x] Consent completion failures: a `UniqueConstraintViolationException` on `(provider, provider_id)` is reported as "email already registered" and redirects to the intended URL instead of `redirects.failure`; `accountAlreadyLinked` from `finishRegistration` returns to the consent screen with the pending registration still in session (`src/Http/Controllers/SocialAuth/SocialRegistrationController.php:59-76`)
- [x] Webhook replay: remember verified Kakao/Google SET `jti` and Naver disconnect `signature` for 30 days and ignore repeats (`src/Verifiers/NaverDisconnectCallbackVerifier.php:33`, `KakaoAccountStatusWebhookVerifier`, `GoogleRiscWebhookVerifier`)
- [ ] Naver disconnect `timestamp` freshness window: blocked until a live callback confirms the timestamp unit (seconds vs milliseconds); see Remaining
- [x] Naver callback page shows `error_description` from the URL; show only the generic translated message (`resources/views/naver-callback.blade.php:80`)

### Fix after choosing the approach

- [ ] Apple callback in redirect mode: `usePopup: false` with `name email` scope makes Apple return a cross-site `form_post` to a `web`-group route → CSRF 419, and the SameSite=Lax session cookie is not sent so state/nonce are missing. Choose popup mode + `postCallback`, or a CSRF-exempt callback that does not rely on the session (`resources/js/social-auth.js:144-151`, `routes/web.php:20`)
- [ ] Apple Server-to-Server Notification shape: code expects body `signedPayload` and an object `events`; community reports say the body key is `payload` and `events` is a JSON string. The test fake has no recorded source (MOCK_RULES). Confirm with a live delivery, then fix verifier and fake (`src/Verifiers/AppleServerNotificationVerifier.php:35,57`, `tests/Feature/AppleServerNotificationTest.php:64`)

### [DECISION] Docs vs code — owner decides which side is right; do not implement until decided

- [ ] Component names and contracts: docs list `ConfigurationValidator`, `SocialUser`, `PendingSocialRegistration`, `ChallengeGenerator`, `ProviderProfileMapper`, `SocialAccountRepository`, `DefaultRegistrationConsent`, events `SocialAccountLinked/Unlinked`, `ProviderContract::key/jsSdkUrl/authenticate(Request)`, config `remote_revoke_enabled`, namespace `Cable8mm\SocialAuth`; code has `SocialAuthServiceProvider::validateConfiguration`, `Data\ProviderUser`, `SocialLoginManager::generateNonce/State`, `AbstractProvider::mapProfile`, `Services\SocialAccountService`, `RegistrationConsentService`, events `SocialAccountConnected/Disconnected` (+ `SocialUserLoggedIn/Registered`), `getName/getJsSdkUrl/verify(array)`, `remote_revoke`, `Cable8mm\LaravelSocialAuth`. TECH_STACK.md heading still says `FRAMEWORK.md`
- [ ] Naver remote revoke: implemented (`NaverProvider::revoke`, `NaverTokenVerifier::revoke`) but PRODUCT_SPEC lists it as out of scope
- [ ] Connect path: ARCHITECTURE describes `SocialLinkController::connect`; the JS never calls it and connects go through `SocialLoginController::callback` + the `connecting_provider` session key, duplicating the logic
- [ ] `<style>` blocks in `naver-callback.blade.php` (incl. `:root`/`body` rules) and `consent.blade.php`; TECH_STACK forbids injecting a `<style>` block
- [ ] Naver nickname: default `nickname_mapping => 'nickname'` stores the Naver nickname in `users.nickname`; PRODUCT_SPEC/ARCHITECTURE say the nickname always comes from the generator (`config/social-auth.php:68`, `SocialLoginManager.php:447`)
- [ ] `users.name` fallback: with no provider name it becomes the generated nickname; PRODUCT_SPEC says GitHub name is null when absent (default `users.name` is NOT NULL) (`SocialLoginManager.php:465`)

### Cleanup

- [ ] Remove unused code/config: `Blade::componentNamespace('...\View\Components')` (directory does not exist), `routes.callback/consent/connect/disconnect`, `providers.*.scopes`, reserved `consent.providers.kakao`, the first `if` in `SocialAccountService::canDisconnect` (covered by the second)
- [ ] Small fixes: `(int)` user-key casts in `SocialAccountService::ensureNotLinkedToOtherUser` break UUID/ULID users; `disconnect` of an unlinked provider throws `providerNotFound`; `updateTokens` skips profile refresh when `store_tokens=false`; `loginUser` always sets remember=true; Workbench comment says "OAuth App" for GitHub
- [ ] Tests: annotate the source of the Naver profile/agreement/token-delete fakes (MOCK_RULES); drop overlap between the two last-login-method tests and between `test_nonce_and_state_generation` and the route tests

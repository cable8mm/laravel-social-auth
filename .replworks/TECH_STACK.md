# TECH_STACK.md — cable8mm/laravel-social-auth

## Stack

- PHP 8.3+, `declare(strict_types=1)` in every file.
- Laravel 12 and 13 (`illuminate/support`, `illuminate/database`, `illuminate/http`).
- Blade for the default (overridable) UI, styled with Tailwind CSS utility classes. The host application is assumed to build Tailwind CSS v4; page background, fonts, and colors outside the package markup are the application's.
- The package does not use `@apply` or inject a `<style>` block from a Blade view. The host application's Tailwind CSS entry must scan the package views.
- Laravel session auth (`Auth::login`, session regenerate on login) — no API/token guard in this package.
- PHPUnit (project owner's explicit choice; not Pest, despite Pest being the usual default elsewhere).
- Laravel Socialite is forbidden — do not add it, and do not add a Socialite-shaped abstraction "just in case."

## Dependencies

- `firebase/php-jwt` (^6.10) — required for Google credential JWT signature verification against Google's JWKS (`JWK::parseKeySet` + `JWT::decode`). This is the one new runtime dependency in the package; justified because implementing JWKS-based RS256 verification by hand is exactly the kind of thing a vetted library should do, and Socialite (which is banned) would have been the only alternative source for this.
- `guzzlehttp/guzzle` is pulled transitively through `illuminate/http`'s `Http` facade — not declared directly.
- Dev-only: `orchestra/testbench` (package test harness), `orchestra/testbench-dusk` + `laravel/dusk` (Workbench browser/E2E harness), `phpunit/phpunit`, `laravel/pint`.
- Dusk is limited to browser-visible package flows against the local Workbench application. Real provider authentication remains an opt-in live-boundary test and is not part of the default suite.
- No other dependency may be added without writing down why here first.

## Package layout

```text
src/
  SocialAuthServiceProvider.php   (bindings, boot-time config validation, publishing, route/view/migration loading)
  Console/           InstallCommand
  Contracts/         ProviderContract, RegistrationConsentContract, NicknameGeneratorContract
  Data/              ProviderUser, ProviderError
  Events/            SocialUserLoggedIn, SocialUserRegistered, SocialAccountConnected, SocialAccountDisconnected,
                     SocialAccountStatusChanged, SocialLoginFailed
  Exceptions/        SocialAuthException (named factories)
  Facades/           SocialAuth
  Http/Controllers/
    SocialAuth/      SocialLoginController, SocialRegistrationController, SocialLinkController,
                     SocialWebhookController
  Models/            SocialAccount
  Providers/         AbstractProvider, GoogleProvider, KakaoProvider, NaverProvider, AppleProvider, GitHubProvider
  Services/          SocialLoginManager, SocialAccountService, RegistrationConsentService, DefaultNicknameGenerator
  Support/           ProviderLabel
  Verifiers/         GoogleCredentialVerifier, KakaoTokenVerifier, NaverTokenVerifier, AppleTokenVerifier, GitHubTokenVerifier,
                     KakaoAccountStatusWebhookVerifier, GoogleRiscWebhookVerifier, AppleServerNotificationVerifier,
                     NaverDisconnectCallbackVerifier
config/social-auth.php
lang/{en,ko}/                   (messages.php, providers.php, views.php — `social-auth::` translations)
routes/web.php
database/migrations/            (social_accounts — auto-loaded)
database/migrations/stubs/      (users credentials/consent and name nullable migrations — publish-only, never auto-run)
resources/js/social-auth.js     (browser integration; imported from vendor by the host app's Vite entry, not published)
resources/views/                (buttons, button, one-tap, connected-accounts components; consent and naver-callback views)
tests/Unit/, tests/Feature/, tests/Browser/ (Dusk)
workbench/                      (Testbench Workbench app for Dusk and live-provider checks)
```

PSR-4 root: `Cable8mm\LaravelSocialAuth\` -> `src/`. Test namespace: `Cable8mm\LaravelSocialAuth\Tests\` -> `tests/`.

## Coding conventions

- All PHP formatted with Laravel Pint (`pint.json` — `laravel` preset) before a task is considered done. Run `vendor/bin/pint` from the package root.
- Favor `final` classes and `readonly` value objects (`ProviderUser`, `ProviderError`) where the class has no legitimate subclassing use case.
- No abstraction without a concrete second use — e.g. no generic "OAuth flow" base class shared by Kakao/Naver until a third code-exchange provider actually shows up (see AGENTS.md DESIGN_RULES).
- User-facing strings are never hardcoded: add a key to both `lang/en/messages.php` and `lang/ko/messages.php` and build the message with `__('social-auth::messages.<key>')`. Blade view copy goes in `lang/{en,ko}/views.php` (`__('social-auth::views.<key>')`), and provider names in views come from `ProviderLabel::for()`, never `ucfirst()`.
- Exception messages that can reach an end user (every `SocialAuthException` factory, e.g. `verificationFailed()`, `accountAlreadyLinked()`, `cannotDisconnectLastMethod()`) must stay generic — never interpolate raw provider error detail into them. Provider error detail is carried separately as a redacted `ProviderError` on `SocialAuthException::providerError()`.

## Testing conventions

- Test harness: `orchestra/testbench`, in-memory SQLite, array session/cache drivers (`tests/TestCase.php`).
- `Http::fake()` is used for every Kakao/Naver/GitHub HTTP call. GitHub fake shapes trace to GitHub's documented REST API and GitHub App user-token schemas (`expires_in`, `refresh_token`, `refresh_token_expires_in`); a live GitHub App login/connect/disconnect has been observed (see TASKS.md). Per MOCK_RULES, fake response shapes must trace to either a recorded live response or the provider's official documented schema — never a guessed shape. The current Kakao email-verification fake shape carries the same [UNVERIFIED] caveat as ARCHITECTURE.md until checked against a live response.
- Google credential verification is tested by generating a throwaway RSA keypair with `openssl_pkey_new()` at test time (inside the tests, e.g. `GoogleCredentialVerifierTest`, `CredentialNonceTest`), signing a real JWT with `firebase/php-jwt`, and serving a matching JWKS via `Http::fake()`. This avoids contacting Google while still exercising the real signature-verification code path.
- Every EXTERNAL_BOUNDARY-touching class (`GoogleCredentialVerifier`, `KakaoTokenVerifier`, `NaverTokenVerifier`, `GitHubTokenVerifier`, and the Providers that wrap them) has unit/feature coverage today, but none of it is a substitute for the E2E-against-a-live-boundary step AGENTS.md requires — see TASKS.md.
- `KakaoAccountStatusWebhookVerifier` has documented-schema coverage today, but live signed SET delivery still requires a public HTTPS endpoint and Kakao's webhook test tool — see TASKS.md.
- `GoogleRiscWebhookVerifier` has documented-schema coverage today, but live signed SET delivery still requires Google Cloud RISC stream registration and a public HTTPS endpoint — see TASKS.md.
- `AppleServerNotificationVerifier` has documented-schema coverage today, but live signed notification delivery still requires Apple Developer server-to-server notification registration and a public HTTPS endpoint — see TASKS.md.
- Run the suite with `vendor/bin/phpunit` (suites are split `Unit` / `Feature` in `phpunit.xml.dist`).

## Configuration validation

`SocialAuthServiceProvider::boot()` loads the package translations and then calls its private `validateConfiguration()`, which rejects `store_tokens=false` combined with `remote_revoke=true` with a translated `SocialAuthException::invalidConfiguration()`. Any new invalid-combination rule goes there, and gets a test in `tests/Unit/ConfigValidationTest.php`.

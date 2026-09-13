# FRAMEWORK.md — cable8mm/laravel-social-auth

## Stack

- PHP 8.3+, `declare(strict_types=1)` in every file.
- Laravel 12 (`illuminate/support`, `illuminate/database`, `illuminate/http`).
- Blade for the default (overridable) UI.
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
  SocialAuthServiceProvider.php
  SocialLoginManager.php
  Contracts/        ProviderContract, RegistrationConsentContract, NicknameGeneratorContract
  Support/           SocialUser, PendingSocialRegistration, ChallengeGenerator,
                      DefaultNicknameGenerator, DefaultRegistrationConsent,
                      ProviderProfileMapper, ConfigurationValidator
  Providers/         GoogleProvider, KakaoProvider, NaverProvider
  Verifiers/         GoogleCredentialVerifier, KakaoTokenVerifier, NaverTokenVerifier
  Models/            SocialAccount
  Repositories/      SocialAccountRepository
  Events/            SocialAccountLinked, SocialAccountUnlinked
  Exceptions/        SocialAuthException and subclasses
  Http/Controllers/  SocialLoginController, SocialLinkController
config/social-auth.php
routes/web.php
database/migrations/            (social_accounts — auto-loaded)
database/migrations/stubs/      (users nullable-columns stub — publish-only, never auto-run)
resources/views/                (buttons, consent, connected-accounts blade partials)
tests/Unit/, tests/Feature/
```

PSR-4 root: `Cable8mm\SocialAuth\` -> `src/`. Test namespace: `Cable8mm\SocialAuth\Tests\` -> `tests/`.

## Coding conventions

- All PHP formatted with Laravel Pint (`pint.json` — `laravel` preset) before a task is considered done. Run `vendor/bin/pint` from the package root.
- Favor `final` classes and `readonly` value objects (`SocialUser`, `PendingSocialRegistration`) where the class has no legitimate subclassing use case.
- No abstraction without a concrete second use — e.g. no generic "OAuth flow" base class shared by Kakao/Naver until a third code-exchange provider actually shows up (see AGENTS.md DESIGN_RULES).
- Exception messages that can reach an end user (`ProviderVerificationException`, `AccountLinkConflictException`, `LastLoginMethodException`) must stay generic — never interpolate raw provider error detail into them. Log detail separately instead.

## Testing conventions

- Test harness: `orchestra/testbench`, in-memory SQLite, array session/cache drivers (`tests/TestCase.php`).
- `Http::fake()` is used for every Kakao/Naver HTTP call. Per MOCK_RULES, fake response shapes must trace to either a recorded live response or the provider's official documented schema — never a guessed shape. The current Kakao email-verification fake shape carries the same [UNVERIFIED] caveat as ARCHITECTURE.md until checked against a live response.
- Google credential verification is tested by generating a throwaway RSA keypair with `openssl_pkey_new()` at test time (`tests/Support/GeneratesGoogleTokens.php`), signing a real JWT with `firebase/php-jwt`, and serving a matching JWKS via `Http::fake()`. This avoids contacting Google while still exercising the real signature-verification code path.
- Every EXTERNAL_BOUNDARY-touching class (`GoogleCredentialVerifier`, `KakaoTokenVerifier`, `NaverTokenVerifier`, and the Providers that wrap them) has unit/feature coverage today, but none of it is a substitute for the E2E-against-a-live-boundary step AGENTS.md requires — see TASKS.md.
- Run the suite with `vendor/bin/phpunit` (suites are split `Unit` / `Feature` in `phpunit.xml.dist`).

## Configuration validation

`ConfigurationValidator::validate()` is called from `SocialAuthServiceProvider::boot()` and must stay a pure function (array in, throws or returns void) so it can be unit tested without booting a full application. Any new invalid-combination rule goes here, not inline in the service provider.

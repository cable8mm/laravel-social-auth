# ARCHITECTURE.md — cable8mm/laravel-social-auth

## EXTERNAL_BOUNDARY (this project)

```text
- Google JWKS endpoint (https://www.googleapis.com/oauth2/v3/certs) — public key fetch for credential JWT verification
- Google Identity Services JS SDK — browser runtime, issues the credential JWT
- Kakao OAuth token endpoint (https://kauth.kakao.com/oauth/token)
- Kakao user profile API (https://kapi.kakao.com/v2/user/me)
- Kakao unlink API (https://kapi.kakao.com/v1/user/unlink)
- Kakao JS SDK — browser runtime, app-switch vs web fallback behavior
- Naver profile API (https://openapi.naver.com/v1/nid/me)
- Naver JS SDK — browser runtime, app-switch vs web fallback behavior
```

Any code calling these directly requires a live observation before a corresponding mock may be written (see FRAMEWORK.md MOCK_RULES). Browser-side SDK behavior (app-switch detection, popup vs redirect) is implemented in Blade/JS and cannot be unit tested — it is out of scope for PHPUnit and would need manual/E2E browser verification instead.

## Components

- `SocialAuthServiceProvider` — registers bindings, validates config at boot (`ConfigurationValidator`), publishes config/views/migrations, loads routes.
- `SocialLoginManager` — orchestration: resolves providers, drives registration/link/unlink flows, owns the "last login method" rule.
- `ProviderContract` — `key()`, `isEnabled()`, `jsSdkUrl()`, `authenticate(Request): SocialUser`, `revoke(SocialAccount): bool`.
- `GoogleProvider` / `KakaoProvider` / `NaverProvider` — implement `ProviderContract`, each delegates credential/token verification to its Verifier.
- `GoogleCredentialVerifier` — JWT signature (via JWKS), issuer, audience, expiry, nonce.
- `KakaoTokenVerifier` — authorization code -> token exchange, profile fetch.
- `NaverTokenVerifier` — access token -> profile fetch, resultcode check.
- `SocialUser` — normalized, already-sanitized DTO returned by every provider.
- `PendingSocialRegistration` — session-serializable snapshot of a `SocialUser`, held between callback and consent completion. No `User` row exists until consent succeeds.
- `SocialAccount` (model) — `social_accounts` table; `access_token` and `refresh_token` use Laravel's `encrypted` cast.
- `SocialAccountRepository` — all `social_accounts` queries; the only place that decides whether tokens are actually persisted (`store_tokens` config).
- `ConfigurationValidator` — pure function, checked at boot: rejects `store_tokens=false` combined with `remote_revoke_enabled=true`.
- `RegistrationConsentContract` / `DefaultRegistrationConsent` — validates terms/privacy/marketing payload.
- `NicknameGeneratorContract` / `DefaultNicknameGenerator` — resolved in the service provider from config (Closure, class-string, or a `User` model method name).
- `ProviderProfileMapper` — applies the per-provider `name_mapping` config; nickname is never provider-sourced.
- `ChallengeGenerator` — issues and session-stores the Google nonce and Kakao/Naver OAuth state values, called from the button views.
- `SocialAccountLinked` / `SocialAccountUnlinked` — events.
- `SocialLoginController` / `SocialLinkController` — HTTP layer; thin, delegate everything to `SocialLoginManager`.

## Data model

`social_accounts`: id, user_id (FK), provider, provider_id, email, name, nickname, avatar, access_token (encrypted, nullable), refresh_token (encrypted, nullable), token_expires_at, raw (json), timestamps.
Unique indexes: `(provider, provider_id)`, `(user_id, provider)`.

`users`: email and password must be nullable. The package never edits the application's own users migration — a publishable stub is provided under the `social-auth-user-columns` tag instead.

## Flows

**Login / registration callback** (`SocialLoginController::callback`):
provider authenticate -> `SocialUser`. If a matching `social_accounts` row exists, log its owner in (`Auth::login` + session regenerate). Otherwise store `PendingSocialRegistration` in session and redirect to the consent screen — no `User` row is created yet.

**Consent completion** (`SocialLoginController::completeConsent`):
Validate consent payload -> inside a DB transaction, create the `User` row (email/email_verified_at per policy, password null) and the `SocialAccount` row together -> log the new user in.

**Explicit linking** (`SocialLinkController::link`, `auth` middleware):
Authenticate provider -> reject if the provider account already belongs to another user, or is already linked to this user -> create `SocialAccount` -> fire `SocialAccountLinked`.

**Unlinking** (`SocialLinkController::unlink`):
Reject if `protect_last_login_method` is true and this is the user's only login method (no password, no email, at most one social account) -> optionally attempt remote revoke (provider-specific — only Kakao implements it in this version) -> delete local row regardless of remote revoke outcome unless `revoke_failure_deletes_local=false` -> fire `SocialAccountUnlinked`.

## Security architecture

- **Google**: replay protection via a nonce minted server-side and stored in session before the GIS button renders (`ChallengeGenerator::googleNonce`), compared against the JWT's `nonce` claim. This is a distinct mechanism from OAuth `state` — Google's credential flow is not a redirect/code exchange.
- **Kakao / Naver**: standard OAuth `state` parameter, minted the same way, compared on callback before any token exchange call is made.
- **Email trust boundary**: Google uses the `email_verified` claim in the JWT. Kakao uses the nested `kakao_account.is_email_verified` and `kakao_account.is_email_valid` flags; local email verification is granted only when both are true. Naver treats email presence in the profile response as sufficient, since Naver does not return unconfirmed emails.
- **Token storage vs remote revoke**: `ConfigurationValidator` rejects `store_tokens=false` combined with `remote_revoke_enabled=true` at boot — remote revoke needs a stored access token, so this combination is a configuration error, not a runtime edge case.
- Sensitive tokens are stripped from the `raw` blob before it is passed into `SocialUser` / persisted (see each Provider's `authenticate()`).

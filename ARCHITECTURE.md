# ARCHITECTURE.md — cable8mm/laravel-social-auth

## EXTERNAL_BOUNDARY (this project)

```text
- Google JWKS endpoint (https://www.googleapis.com/oauth2/v3/certs) — public key fetch for credential JWT verification
- Google RISC endpoint and SET delivery — Cross-Account Protection security event notifications
- Google Identity Services JS SDK — browser runtime, issues the credential JWT
- Kakao OAuth token endpoint (https://kauth.kakao.com/oauth/token)
- Kakao user profile API (https://kapi.kakao.com/v2/user/me)
- Kakao unlink API (https://kapi.kakao.com/v1/user/unlink)
- Kakao account status webhook and JWKS (https://kauth.kakao.com/.well-known/jwks.json)
- Kakao JS SDK — browser runtime, app-switch vs web fallback behavior
- Naver profile API (https://openapi.naver.com/v1/nid/me)
- Naver disconnect callback (encrypted user ID + HMAC-SHA256)
- Naver JS SDK — browser runtime, app-switch vs web fallback behavior
- Apple authorization/token/revoke endpoints (https://appleid.apple.com)
- Apple Sign in with Apple Server-to-Server Notification endpoint and JWS delivery
- Apple JWKS endpoint (https://appleid.apple.com/auth/keys)
- Sign in with Apple JS SDK — browser runtime and Apple Account authentication
```

Any code calling these directly requires a live observation before a corresponding mock may be written (see FRAMEWORK.md MOCK_RULES). Browser-side SDK behavior (app-switch detection, popup vs redirect) is implemented in Blade/JS and cannot be unit tested — it is out of scope for PHPUnit and would need manual/E2E browser verification instead.

## Components

- `SocialAuthServiceProvider` — registers bindings and `social-auth:install`, validates config at boot (`ConfigurationValidator`), publishes config/views/migrations, and loads routes.
- `InstallCommand` — publishes the package config and a timestamped nullable user-columns migration; it does not run application migrations.
- `resources/js/social-auth.js` — browser-side provider SDK orchestration, imported by the host application's existing Vite entry.
- `SocialLoginManager` — orchestration: resolves providers, drives registration/link/unlink flows, owns the "last login method" rule.
- `ProviderContract` — `key()`, `isEnabled()`, `jsSdkUrl()`, `authenticate(Request): SocialUser`, `revoke(SocialAccount): bool`.
- `GoogleProvider` / `KakaoProvider` / `NaverProvider` / `AppleProvider` — implement `ProviderContract`, each delegates credential/token verification to its Verifier.
- `GoogleCredentialVerifier` — JWT signature (via JWKS), issuer, audience, expiry, nonce.
- `KakaoTokenVerifier` — authorization code -> token exchange, profile fetch.
- `NaverTokenVerifier` — access token -> profile fetch, resultcode check.
- `AppleTokenVerifier` — authorization code exchange, client-secret JWT generation, identity-token verification, and revoke.
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
- `SocialLoginController` — nonce/state endpoints and provider callback; thin, delegates to `SocialLoginManager`.
- `SocialRegistrationController` — consent screen and registration completion; thin, delegates to `SocialLoginManager`.
- `SocialLinkController` — authenticated connect/disconnect endpoints; thin, delegates to `SocialLoginManager`.
- `SocialWebhookController` — verifies Kakao account-status SET payloads and applies safe default token/connection cleanup.
- `GoogleRiscWebhookVerifier` — verifies Google RISC SET signatures, issuer, audience, and event structure.
- `NaverDisconnectCallbackVerifier` — verifies the client ID, HMAC signature, and decrypts Naver's AES-128-CBC user identifier.
- `AppleServerNotificationVerifier` — verifies Apple signed notification JWS signatures with Apple's JWKS, issuer, audience, and event structure.

## Data model

`social_accounts`: id, user_id (FK), provider, provider_id, email, name, nickname, avatar, access_token (encrypted, nullable), refresh_token (encrypted, nullable), token_expires_at, raw (json), timestamps.
Unique indexes: `(provider, provider_id)`, `(user_id, provider)`.

`users`: email and password must be nullable. The package never edits the application's own users migration — a publishable stub is provided under the `social-auth-user-columns` tag instead. That stub also adds nullable timestamp columns `terms_accepted_at`, `privacy_policy_accepted_at`, and `marketing_accepted_at`.

## Flows

**Login / registration callback** (`SocialLoginController::callback`):
provider authenticate -> `SocialUser`. The browser stores the current same-origin page as the intended destination before provider authentication. If a matching `social_accounts` row exists, log its owner in (`Auth::login` + session regenerate) and consume that destination. Otherwise store `PendingSocialRegistration` in session and redirect to the consent screen — no `User` row is created yet. Consent completion consumes the same destination after creating and logging in the user.

If no matching social account exists but the provider reports an email already used by a local user, the manager rejects the callback before storing `PendingSocialRegistration`. The controller returns to the configured failure redirect (normally `/login`) with a safe message; it never automatically merges or links the accounts. The authenticated user must explicitly connect the provider from the profile.

**Consent completion** (`SocialRegistrationController::store`):
Validate consent payload -> inside a DB transaction, create the `User` row (email/email_verified_at per policy, password null, and mapped consent fields) and the `SocialAccount` row together -> log the new user in.

Consent keys are mapped to user columns by `config/social-auth.php` under `consent.user_fields`. The default mapping is `terms_of_service` -> `terms_accepted_at`, `privacy_policy` -> `privacy_policy_accepted_at`, and `marketing` -> `marketing_accepted_at`. Accepted terms receive the current timestamp; unchecked terms receive `null`. A field can be set to `null` to opt out of persisting that consent value.

**Explicit linking** (`SocialLinkController::connect`, `auth` middleware):
Authenticate provider -> reject if the provider account already belongs to another user, or is already linked to this user -> create `SocialAccount` -> fire `SocialAccountLinked`.

**Unlinking** (`SocialLinkController::disconnect`):
Reject if `protect_last_login_method` is true and this is the user's only login method (no password, no email, at most one social account) -> attempt provider-specific remote revoke when a stored access token exists -> delete local row regardless of remote revoke outcome -> fire `SocialAccountUnlinked`.

## Security architecture

- **Google**: replay protection via a nonce minted server-side and stored in session before the GIS button renders (`ChallengeGenerator::googleNonce`), compared against the JWT's `nonce` claim. This is a distinct mechanism from OAuth `state` — Google's credential flow is not a redirect/code exchange.
- **Google RISC**: verifies signed historical security event tokens with Google's JWKS, issuer, and the configured Google client ID as audience. The package dispatches events; application listeners own session termination and account protection.
- **Kakao / Naver**: standard OAuth `state` parameter, minted the same way, compared on callback before any token exchange call is made.
- **Kakao account status webhook**: verifies SET RS256 signature with Kakao JWKS, issuer, audience, and event structure before dispatching or mutating a local social account.
- **Naver disconnect callback**: verifies the client ID and HMAC-SHA256 signature before decrypting the AES-128-CBC user identifier and removing its local social account.
- **Apple**: standard OAuth `state` plus an ID-token `nonce`, both compared on callback before accepting the identity.
- **Apple Server-to-Server Notifications**: verifies the signed `signedPayload` JWS with Apple's JWKS, issuer, audience, and event type. The package dispatches status events and removes only the local Apple connection for `consent-revoked` and `account-deleted`.
- **Email trust boundary**: Google uses the `email_verified` claim in the JWT. Kakao uses the nested `kakao_account.is_email_verified` and `kakao_account.is_email_valid` flags; local email verification is granted only when both are true. Naver treats email presence in the profile response as sufficient, since Naver does not return unconfirmed emails.
- **Token storage vs remote revoke**: the published config enables encrypted token storage and remote revoke by default. `ConfigurationValidator` rejects `store_tokens=false` combined with `remote_revoke_enabled=true` if an application changes those policies in its published config.
- Sensitive tokens are stripped from the `raw` blob before it is passed into `SocialUser` / persisted (see each Provider's `authenticate()`).

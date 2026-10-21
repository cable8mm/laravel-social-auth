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
- Naver Login Plus agreement API (https://openapi.naver.com/v1/nid/agreement)
- Naver disconnect callback (encrypted user ID + HMAC-SHA256)
- Naver JS SDK — browser runtime, app-switch vs web fallback behavior
- Apple authorization/token/revoke endpoints (https://appleid.apple.com)
- Apple Sign in with Apple Server-to-Server Notification endpoint and JWS delivery
- Apple JWKS endpoint (https://appleid.apple.com/auth/keys)
- Sign in with Apple JS SDK — browser runtime and Apple Account authentication
- GitHub App user-authorization endpoints: authorize, code exchange, and token refresh (https://github.com/login/oauth/authorize, https://github.com/login/oauth/access_token)
- GitHub REST API user, emails, and grant-delete endpoints (https://api.github.com/user, /user/emails, /applications/{client_id}/grant)
```

Any code calling these directly requires a live observation before a corresponding mock may be written (see AGENTS.md MOCK_RULES). Browser-side SDK behavior (app-switch detection, popup vs redirect) is implemented in Blade/JS and cannot be unit tested — it is out of scope for PHPUnit and would need manual/E2E browser verification instead.

## Components

- `SocialAuthServiceProvider` — registers the singletons (`SocialAccountService`, `RegistrationConsentContract`, `NicknameGeneratorContract`, `SocialLoginManager`) and `social-auth:install`; at boot loads translations, then validates the config (`validateConfiguration()`: rejects `store_tokens=false` with `remote_revoke=true`), loads routes/views/migrations, registers the anonymous Blade components under `social-auth`, and publishes config, views, translations, and migrations.
- `InstallCommand` — publishes the package config and the nullable user-column migrations (`social-auth-user-columns`); it does not run application migrations.
- `resources/js/social-auth.js` — browser-side provider SDK orchestration, imported by the host application's existing Vite entry.
- `SocialLoginManager` (`Services\`, also behind the `SocialAuth` facade) — orchestration: builds one provider per config entry, handles the callback, registration, connect, and disconnect flows, issues the nonce (`generateNonce()`) and OAuth state (`generateState()`) into the session, keeps the pending registration in the session, remembers and consumes the intended URL, and creates the user (name mapping, nickname, email-verification policy, consent timestamps).
- `ProviderContract` — `getName()`, `isEnabled()`, `getClientId()`, `getJsSdkUrl()`, `getConfig()`, `verify(array $payload): ProviderUser`, `revoke(SocialAccount): bool`, `mapProfile(ProviderUser): array`. `revoke` receives the stored account so a provider can use its refresh token and expiry, not just the access token.
- `AbstractProvider` — config-backed defaults for every provider: enabled flag, client IDs, SDK URL, `mapProfile()` (per-provider `name_mapping` and `nickname_mapping`), `sanitizeRaw()`, and a no-op `revoke()`.
- `GoogleProvider` / `KakaoProvider` / `NaverProvider` / `AppleProvider` / `GitHubProvider` — `verify()` checks the session nonce (Google, Apple) and/or OAuth state (Kakao, Naver, GitHub, Apple), then delegates to the provider's verifier. `NaverProvider::withProviderConsent()` adds Login Plus agreements when provider-managed consent is enabled.
- `GoogleCredentialVerifier` — JWT signature (via JWKS), issuer, audience, expiry, subject, nonce.
- `KakaoTokenVerifier` — authorization code -> token exchange, profile fetch, and unlink revoke (`Authorization: Bearer`).
- `NaverTokenVerifier` — access token -> profile fetch, resultcode check, optional Login Plus agreement lookup, and token-delete revoke.
- `AppleTokenVerifier` — authorization code exchange, client-secret JWT generation, identity-token verification, and revoke.
- `GitHubTokenVerifier` — authorization code -> token exchange (GitHub returns OAuth errors with HTTP 200, so an `error` body is treated as a failure), user and email-list fetch, refresh-token exchange, and grant-delete revoke (HTTP Basic auth with client ID/secret).
- `ProviderUser` (`Data\`) — normalized, already-sanitized DTO returned by every provider. Its `toArray()` is the pending registration kept in the session between callback and consent completion; no `User` row exists until consent succeeds.
- `ProviderError` (`Data\`) — redacted provider error details attached to `SocialAuthException`.
- `SocialAccount` (model) — table from `social-auth.table` (default `social_accounts`); `access_token` and `refresh_token` use Laravel's `encrypted` cast and are hidden with `raw`.
- `SocialAccountService` (`Services\`) — all `social_accounts` queries and writes; the only place that decides whether tokens are actually persisted (`store_tokens` config); owns the linked-to-another-user check and the last-login-method rule (`canDisconnect()`).
- `RegistrationConsentContract` / `RegistrationConsentService` — reads required and optional terms from config and validates the terms/privacy/marketing payload.
- `NicknameGeneratorContract` / `DefaultNicknameGenerator` — resolved in the service provider from config (null for the default `user_` + 8 random characters, a Closure, a `Class@method` string, or a class name).
- Events — `SocialUserLoggedIn`, `SocialUserRegistered`, `SocialAccountConnected`, `SocialAccountDisconnected` (with `remoteRevoked`), `SocialAccountStatusChanged`, `SocialLoginFailed`.
- `lang/{en,ko}/messages.php`, `lang/{en,ko}/providers.php`, `lang/{en,ko}/views.php` — package translations loaded under the `social-auth` namespace (before boot-time config validation, so configuration errors are translated) and publishable with the `social-auth-lang` tag. Every `SocialAuthException` factory builds its message with `__('social-auth::messages.<key>')`; the technical failure reason is stored separately and exposed by `SocialAuthException::reason()`. The Blade views take every user-facing string from `social-auth::views.*` and provider names from `ProviderLabel`.
- `ProviderLabel` — returns the translated provider name (`social-auth::providers.<key>`), falling back to `ucfirst($provider)` for unknown providers; used as the `:provider` parameter in messages and views.
- `SocialLoginController` — nonce/state endpoints (which also remember the intended URL and, for `context=connect`, the connecting provider) and the provider callback (a Naver `GET` renders the fragment hand-off page); thin, delegates to `SocialLoginManager`.
- `SocialRegistrationController` — consent screen and registration completion; thin, delegates to `SocialLoginManager`.
- `SocialLinkController` — authenticated `POST /{provider}/connect` and `DELETE /{provider}/disconnect`; thin, delegates to `SocialLoginManager`.
- `SocialWebhookController` — Kakao account-status, Google RISC, Apple server-to-server, and Naver disconnect endpoints: verifies each delivery, ignores replays, dispatches `SocialAccountStatusChanged`, and applies the safe default token/connection cleanup.
- `KakaoAccountStatusWebhookVerifier` — verifies Kakao account-status SET signatures with Kakao's JWKS, issuer, audience, and event structure.
- `GoogleRiscWebhookVerifier` — verifies Google RISC SET signatures, issuer, audience, and event structure.
- `NaverDisconnectCallbackVerifier` — verifies the client ID, HMAC signature, and decrypts Naver's AES-128-CBC user identifier.
- `AppleServerNotificationVerifier` — verifies Apple signed notification JWS signatures with Apple's JWKS, issuer, audience, and event structure.
- Blade — anonymous components `x-social-auth::buttons`, `button`, `one-tap`, `connected-accounts`, and the `consent` and `naver-callback` views; styled only with Tailwind CSS utility classes from the host application's build.

## Data model

`social_accounts`: id, user_id (FK), provider, provider_id, email, name, nickname, avatar, access_token (encrypted, nullable), refresh_token (encrypted, nullable), token_expires_at, raw (json), timestamps.
Unique indexes: `(provider, provider_id)`, `(user_id, provider)`.

`users`: name, email, and password must be nullable. The package never edits the application's own users migration — publishable migrations are provided under the `social-auth-user-columns` tag instead. The credentials migration makes email and password nullable and adds nullable timestamp columns `terms_accepted_at`, `privacy_policy_accepted_at`, and `marketing_accepted_at`. The name migration is a separate file so applications that already ran the credentials migration can publish it on its own.

`users.name` gets the provider name mapped by `name_mapping`, or null; a default name is the application's decision. `users.nickname` gets the provider field mapped by `nickname_mapping` (default: Naver's `nickname`; none for Kakao, Google, GitHub, Apple), or the nickname generator when that value is empty.

## Flows

**Login / registration callback** (`SocialLoginController::callback`):
provider `verify()` -> `ProviderUser`. The browser stores the current same-origin page as the intended destination before provider authentication. If a matching `social_accounts` row exists, log its owner in (`Auth::login` + session regenerate) and consume that destination. Otherwise store the `ProviderUser` array as the pending registration in session and redirect to the consent screen — no `User` row is created yet. Consent completion consumes the same destination after creating and logging in the user. The consent screen's own URL is never stored as the destination: pages rendered on it (for example the layout's guest Google One Tap, which fetches a nonce with the current page as `redirect`) must not replace the page the sign-up started from.

If no matching social account exists but the provider reports an email already used by a local user, the manager rejects the callback before storing the pending registration. The controller returns to the configured failure redirect (normally `/login`) with a safe message; it never automatically merges or links the accounts. The authenticated user must explicitly connect the provider from the profile.

When a non-connect callback throws `SocialAuthException`, `SocialLoginController` dispatches `SocialLoginFailed` once with the route provider, the exception's `failureReason()`, and the exception, then returns the same JSON 422 or failure redirect as before. Reasons are assigned by the exception factory: `emailAlreadyRegistered` -> `existing_email_unlinked`; `verificationFailed`, `invalidState`, `invalidNonce` -> `invalid_credential`; `providerConsentRequired` -> `consent_rejected`; everything else, including `providerRequestFailed` (provider API 5xx), -> `provider_error`. Connect-flow failures do not dispatch it.

When a provider HTTP call fails (Kakao token/profile, Naver profile/agreement, Apple code exchange, GitHub token/user/emails), the verifier attaches `ProviderError::fromResponse()` to the exception: HTTP status plus the provider's `error`, error code (`error_code`, `code`, `resultcode`, or `detail.reason`), and description (`error_description`, `msg`, or `message`). Values sent with the request (authorization code, access token, client secret) and `code=`/`token=`-style fragments are replaced with `[redacted]`, and each field is capped at 500 characters. The details are available via `SocialAuthException::providerError()` and never change the exception message. Google credential verification makes no provider token exchange, so it has no provider error body to keep.

**Consent completion** (`SocialRegistrationController::store`):
Validate consent payload -> inside a DB transaction, create the `User` row (email/email_verified_at per policy, password null, and mapped consent fields) and the `SocialAccount` row together -> log the new user in.

If the provider account was linked, or the email registered, by another request after the callback (detected up front or as a unique-constraint violation), the pending registration is cleared and the user returns to the failure redirect with `account_already_linked` or `email_already_registered`. Only missing required consent keeps the pending registration and returns to the consent screen.

Consent term labels default to `social-auth::views.consent.terms.<key>`; a non-null `label` in the term config overrides the translation.

Consent keys are mapped to user columns by `config/social-auth.php` under `consent.user_fields`. The default mapping is `terms_of_service` -> `terms_accepted_at`, `privacy_policy` -> `privacy_policy_accepted_at`, and `marketing` -> `marketing_accepted_at`. Accepted terms receive the current timestamp; unchecked terms receive `null`. A field can be set to `null` to opt out of persisting that consent value.

Provider-managed consent is opt-in under `consent.providers`. For Naver, the application maps its Naver Login Plus `termCode` values to the local consent keys. When enabled, `NaverTokenVerifier` fetches the agreement list using the access token, and registration proceeds without the local consent screen only when every required local term has a matching provider agreement. Provider agreement timestamps are stored in the configured user consent fields. Missing required agreements or agreement API failures stop registration and return a safe error to the failure redirect. The Kakao configuration namespace is reserved for a future Kakao Sync implementation.

**Explicit linking** (authenticated user, from the profile's connect buttons):
The connect button fetches the nonce or state with `context=connect`, which stores the provider in the `connecting_provider` session key. The provider result then reaches `SocialLoginController::callback` (Google: the JS posts the credential; Kakao, Naver, GitHub, Apple: the provider redirects back), which connects instead of logging in when the user is authenticated and `connecting_provider` matches the route provider, then clears the key. `POST /{provider}/connect` (`SocialLinkController::connect`, `auth` middleware) runs the same connect for a direct request; the package JS does not call it. Either way: verify provider -> reject if the provider account already belongs to another user, or is already linked to this user -> create `SocialAccount` -> fire `SocialAccountConnected`. Connect failures return to `redirects.connect_success` and do not dispatch `SocialLoginFailed`. Known issue: `connecting_provider` is one global key that a later nonce/state fetch can overwrite (TASKS.md "Fix: connect intent").

**Unlinking** (`SocialLinkController::disconnect`):
Reject if `protect_last_login_method` is true and this is the user's only login method (no password and at most one social account; an email alone cannot log in, so it does not count) -> attempt provider-specific remote revoke with the stored `SocialAccount` when it has an access token -> for GitHub, if the access token is expired (or expires within 60 seconds) and a refresh token exists, refresh first and revoke with the new access token; the refreshed tokens are not persisted because the row is deleted next -> delete local row regardless of remote revoke outcome -> fire `SocialAccountDisconnected`.

## Security architecture

- **Google**: replay protection via a nonce minted server-side and stored in session before the GIS button renders (`SocialLoginManager::generateNonce()` via `GET /social-auth/nonce`), compared against the JWT's `nonce` claim. A callback whose session holds no nonce is rejected. This is a distinct mechanism from OAuth `state` — Google's credential flow is not a redirect/code exchange.
- **Google RISC**: verifies signed historical security event tokens with Google's JWKS, issuer, and the configured Google client ID as audience. The package dispatches events; application listeners own session termination and account protection.
- **Kakao / Naver**: standard OAuth `state` parameter, minted the same way, compared on callback before any token exchange call is made.
- **Kakao account status webhook**: verifies SET RS256 signature with Kakao JWKS, issuer, audience, and event structure before dispatching or mutating a local social account.
- **Naver disconnect callback**: verifies the client ID and HMAC-SHA256 signature before decrypting the AES-128-CBC user identifier and removing its local social account.
- **Webhook replay**: after verification, `SocialWebhookController` remembers each Kakao/Google SET `jti` and each Naver disconnect `signature` in the cache for 30 days; a repeated delivery gets the normal success response and changes nothing.
- **GitHub**: standard OAuth `state`, minted the same way, compared on callback before any token exchange call is made. The browser is redirected to GitHub's authorize URL (no JS SDK); GitHub redirects back to the callback with `code` and `state` via GET.
- **Apple**: standard OAuth `state` plus an ID-token `nonce`, both compared on callback before accepting the identity. A callback whose session holds no nonce is rejected before the code exchange.
- **Apple Server-to-Server Notifications**: verifies the signed `signedPayload` JWS with Apple's JWKS, issuer, audience, and event type. The package dispatches status events and removes only the local Apple connection for `consent-revoked` and `account-deleted`.
- **Email trust boundary**: Google uses the `email_verified` claim in the JWT. Kakao uses the nested `kakao_account.is_email_verified` and `kakao_account.is_email_valid` flags; local email verification is granted only when both are true. Naver treats email presence in the profile response as sufficient, since Naver does not return unconfirmed emails. GitHub ignores the public profile `email` and uses only the `/user/emails` entry with `primary: true` and `verified: true`; when none exists the email is null.
- **Token storage vs remote revoke**: the published config enables encrypted token storage and remote revoke by default. `SocialAuthServiceProvider` rejects `store_tokens=false` combined with `remote_revoke=true` at boot if an application changes those policies in its published config.
- Sensitive tokens are stripped from the `raw` blob before it is passed into `ProviderUser` / persisted (see each provider's `verify()`).
- GitHub keeps only `id`, `login`, `name`, `avatar_url`, `html_url`, `type`, and `created_at` in `raw`. The full `/user` response carries dozens of API URL templates; together with the tokens it pushes the pending registration past the 4KB browser cookie limit when the application uses the `cookie` session driver, which silently drops the session. A `name_mapping` key for GitHub must therefore be one of these fields.

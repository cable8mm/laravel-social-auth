<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Exceptions;

use Cable8mm\LaravelSocialAuth\Data\ProviderError;
use Cable8mm\LaravelSocialAuth\Events\SocialLoginFailed;
use Cable8mm\LaravelSocialAuth\Support\ProviderLabel;
use Exception;

class SocialAuthException extends Exception
{
    private string $failureReason = SocialLoginFailed::PROVIDER_ERROR;

    private ?string $reason = null;

    private ?ProviderError $providerError = null;

    /**
     * Machine-readable reason used for the SocialLoginFailed event.
     */
    public function failureReason(): string
    {
        return $this->failureReason;
    }

    /**
     * Technical reason from the verifier (e.g. "Token exchange failed"). Not part of the user-facing message.
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * Redacted error details returned by the provider, if any. Not part of the user-facing message.
     */
    public function providerError(): ?ProviderError
    {
        return $this->providerError;
    }

    private function withReason(string $reason): self
    {
        $this->reason = $reason !== '' ? $reason : null;

        return $this;
    }

    private function withProviderError(?ProviderError $providerError): self
    {
        $this->providerError = $providerError;

        return $this;
    }

    private function withFailureReason(string $reason): self
    {
        $this->failureReason = $reason;

        return $this;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function message(string $key, array $replace = []): self
    {
        return new self((string) __("social-auth::messages.{$key}", $replace));
    }

    public static function invalidConfiguration(string $message): self
    {
        return self::message('invalid_configuration', ['message' => $message]);
    }

    public static function providerNotEnabled(string $provider): self
    {
        return self::message('provider_not_enabled', ['provider' => ProviderLabel::for($provider)]);
    }

    public static function providerNotFound(string $provider): self
    {
        return self::message('provider_not_found', ['provider' => ProviderLabel::for($provider)]);
    }

    public static function verificationFailed(string $provider, string $reason = '', ?ProviderError $providerError = null): self
    {
        return self::message('verification_failed', ['provider' => ProviderLabel::for($provider)])
            ->withReason($reason)
            ->withFailureReason(SocialLoginFailed::INVALID_CREDENTIAL)
            ->withProviderError($providerError);
    }

    /**
     * Provider API returned a server error. Reported as a provider error.
     */
    public static function providerRequestFailed(string $provider, string $reason, ?ProviderError $providerError = null): self
    {
        return self::message('provider_request_failed', ['provider' => ProviderLabel::for($provider)])
            ->withReason($reason)
            ->withProviderError($providerError);
    }

    public static function accountAlreadyLinked(string $provider): self
    {
        return self::message('account_already_linked', ['provider' => ProviderLabel::for($provider)]);
    }

    public static function alreadyConnected(string $provider): self
    {
        return self::message('already_connected', ['provider' => ProviderLabel::for($provider)]);
    }

    public static function cannotDisconnectLastMethod(): self
    {
        return self::message('cannot_disconnect_last_method');
    }

    public static function consentRequired(): self
    {
        return self::message('consent_required');
    }

    public static function emailAlreadyRegistered(): self
    {
        return self::message('email_already_registered')
            ->withFailureReason(SocialLoginFailed::EXISTING_EMAIL_UNLINKED);
    }

    public static function providerConsentRequired(string $provider): self
    {
        return self::message('provider_consent_required', ['provider' => ProviderLabel::for($provider)])
            ->withFailureReason(SocialLoginFailed::CONSENT_REJECTED);
    }

    public static function unauthenticated(): self
    {
        return self::message('unauthenticated');
    }

    public static function invalidState(): self
    {
        return self::message('invalid_state')
            ->withFailureReason(SocialLoginFailed::INVALID_CREDENTIAL);
    }

    public static function invalidNonce(): self
    {
        return self::message('invalid_nonce')
            ->withFailureReason(SocialLoginFailed::INVALID_CREDENTIAL);
    }
}

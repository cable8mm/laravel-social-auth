<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Exceptions;

use Exception;

class SocialAuthException extends Exception
{
    public static function invalidConfiguration(string $message): self
    {
        return new self("Social Auth configuration error: {$message}");
    }

    public static function providerNotEnabled(string $provider): self
    {
        return new self("Provider [{$provider}] is not enabled.");
    }

    public static function providerNotFound(string $provider): self
    {
        return new self("Provider [{$provider}] is not registered.");
    }

    public static function verificationFailed(string $provider, string $reason = ''): self
    {
        $msg = "Verification failed for provider [{$provider}]";
        if ($reason !== '') {
            $msg .= ": {$reason}";
        }

        return new self($msg);
    }

    public static function accountAlreadyLinked(string $provider): self
    {
        return new self("This {$provider} account is already linked to another user.");
    }

    public static function alreadyConnected(string $provider): self
    {
        return new self("You already have a {$provider} account connected.");
    }

    public static function cannotDisconnectLastMethod(): self
    {
        return new self('Cannot disconnect the last login method. Please set a password or connect another account first.');
    }

    public static function consentRequired(): self
    {
        return new self('You must accept the required terms and conditions to complete registration.');
    }

    public static function unauthenticated(): self
    {
        return new self('Authentication required.');
    }

    public static function invalidState(): self
    {
        return new self('Invalid or missing state parameter. Please try again.');
    }

    public static function invalidNonce(): self
    {
        return new self('Invalid or missing nonce. Please try again.');
    }
}

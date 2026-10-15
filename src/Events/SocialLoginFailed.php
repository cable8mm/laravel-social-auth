<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

class SocialLoginFailed
{
    use Dispatchable;

    /** A local user already owns the provider email; automatic merge was refused. */
    public const EXISTING_EMAIL_UNLINKED = 'existing_email_unlinked';

    /** The provider credential, authorization code, state, or nonce failed verification. */
    public const INVALID_CREDENTIAL = 'invalid_credential';

    /** A provider API call failed, or any other social auth error occurred. */
    public const PROVIDER_ERROR = 'provider_error';

    /** Provider-managed consent did not confirm every required term. */
    public const CONSENT_REJECTED = 'consent_rejected';

    public function __construct(
        public readonly string $provider,
        public readonly string $reason,
        public readonly ?Throwable $exception = null,
    ) {}
}

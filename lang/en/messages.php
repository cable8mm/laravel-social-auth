<?php

declare(strict_types=1);

return [
    'invalid_configuration' => 'Social Auth configuration error: :message',
    'provider_not_enabled' => 'Provider [:provider] is not enabled.',
    'provider_not_found' => 'Provider [:provider] is not registered.',
    'verification_failed' => 'Verification failed for provider [:provider]',
    'provider_request_failed' => 'Verification failed for provider [:provider]',
    'account_already_linked' => 'This :provider account is already linked to another user.',
    'already_connected' => 'You already have a :provider account connected.',
    'cannot_disconnect_last_method' => 'Cannot disconnect the last login method. Please set a password or connect another account first.',
    'consent_required' => 'You must accept the required terms and conditions to complete registration.',
    'email_already_registered' => 'This email address is already registered. Sign in to your existing account, then connect this social account from your profile.',
    'provider_consent_required' => 'We could not confirm the required terms consent from :provider. Please try again.',
    'unauthenticated' => 'Authentication required.',
    'invalid_state' => 'Invalid or missing state parameter. Please try again.',
    'invalid_nonce' => 'Invalid or missing nonce. Please try again.',
    'connected' => ':provider account connected.',
    'disconnected' => ':provider account disconnected.',
];

<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Contracts;

interface RegistrationConsentContract
{
    /**
     * Validate that all required terms have been accepted.
     *
     * @param  array<string, bool>  $accepted  key => accepted
     */
    public function validate(array $accepted): bool;

    /**
     * Return list of required term keys.
     *
     * @return list<string>
     */
    public function requiredTerms(): array;

    /**
     * Return all terms configuration.
     *
     * @return array<string, array{label: string, url: ?string, required: bool}>
     */
    public function allTerms(): array;
}

<?php

declare(strict_types=1);

namespace Cable8mm\LaravelSocialAuth\Services;

use Cable8mm\LaravelSocialAuth\Contracts\RegistrationConsentContract;

class RegistrationConsentService implements RegistrationConsentContract
{
    public function validate(array $accepted): bool
    {
        foreach ($this->requiredTerms() as $key) {
            if (empty($accepted[$key])) {
                return false;
            }
        }

        return true;
    }

    public function requiredTerms(): array
    {
        $required = [];
        $terms = config('social-auth.consent.required_terms', []);

        foreach ($terms as $key => $term) {
            if ($term['required'] ?? true) {
                $required[] = $key;
            }
        }

        return $required;
    }

    public function allTerms(): array
    {
        $required = config('social-auth.consent.required_terms', []);
        $optional = config('social-auth.consent.optional_terms', []);

        return array_merge($required, $optional);
    }
}

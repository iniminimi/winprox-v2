<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Facility-pariteit (V1 Properties): minstens locatienaam óf volledig adres
 * (straat + postcode + plaats).
 */
class LocationMinimumIdentity implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isSatisfied($this->data)) {
            $fail(__('locations.errors.name_or_address_required'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isSatisfied(array $data): bool
    {
        if (trim((string) ($data['name'] ?? '')) !== '') {
            return true;
        }

        return trim((string) ($data['street'] ?? '')) !== ''
            && trim((string) ($data['postal_code'] ?? '')) !== ''
            && trim((string) ($data['city'] ?? '')) !== '';
    }
}

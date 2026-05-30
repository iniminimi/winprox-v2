<?php

namespace App\Http\Requests\Locations;

use App\Rules\LocationMinimumIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as IlluminateValidator;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:32'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'country_code.size' => __('locations.errors.country_code_invalid'),
            'country_code.alpha' => __('locations.errors.country_code_invalid'),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function validatePayload(array $attributes): array
    {
        $validator = Validator::make(
            $attributes,
            self::ruleSet(),
            self::messageSet(),
        );

        self::applyMinimumIdentityCheck($validator);

        return $validator->validate();
    }

    public static function applyMinimumIdentityCheck(IlluminateValidator $validator): void
    {
        $validator->after(function (IlluminateValidator $validator): void {
            if (! LocationMinimumIdentity::isSatisfied($validator->getData())) {
                $validator->errors()->add('name', __('locations.errors.name_or_address_required'));
            }
        });
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    protected function withValidator(IlluminateValidator $validator): void
    {
        self::applyMinimumIdentityCheck($validator);
    }
}

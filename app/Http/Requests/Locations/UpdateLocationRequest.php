<?php

namespace App\Http\Requests\Locations;

class UpdateLocationRequest extends StoreLocationRequest
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function validatePayload(array $attributes): array
    {
        return parent::validatePayload($attributes);
    }
}

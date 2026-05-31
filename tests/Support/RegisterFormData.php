<?php

namespace Tests\Support;

final class RegisterFormData
{
    /**
     * @return array<string, mixed>
     */
    public static function valid(): array
    {
        return [
            'organization' => 'Nieuwe Facility',
            'phone' => '+32475123456',
            'street' => 'Bosrandstraat',
            'house_number' => '10',
            'postal_code' => '8000',
            'city' => 'Brugge',
            'country_code' => 'BE',
            'name' => 'Nieuwe Beheerder',
            'email' => 'nieuw@winprox.test',
            'password' => 'wachtwoord123',
            'password_confirmation' => 'wachtwoord123',
            'accept_terms' => true,
        ];
    }
}

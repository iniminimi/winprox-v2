<?php

declare(strict_types=1);

namespace App\Data\Marketing;

final class MunicipalPromoLetterData
{
    public function __construct(
        public readonly string $name,
        public readonly string $municipalityType,
        public readonly string $streetAddress,
        public readonly string $postalCode,
        public readonly string $municipality,
        public readonly string $province,
        public readonly ?string $phone,
        public readonly ?string $email,
    ) {}

    public function slug(): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($this->name)) ?: 'gemeente';

        return trim($slug, '-');
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        return array_values(array_filter([
            $this->name,
            'T.a.v. het college',
            $this->streetAddress,
            trim($this->postalCode.' '.$this->municipality),
        ], static fn (string $line): bool => $line !== ''));
    }
}

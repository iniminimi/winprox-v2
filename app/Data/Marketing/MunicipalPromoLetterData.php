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
        $nameSlug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($this->name)) ?: 'gemeente';
        $nameSlug = str_replace('-', '_', trim($nameSlug, '-'));

        $postalCode = preg_replace('/\D/', '', $this->postalCode);
        if ($postalCode === '') {
            return $nameSlug;
        }

        return $postalCode.'_'.$nameSlug;
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        return array_values(array_filter([
            'T.a.v. het college '.$this->name,
            $this->streetAddress,
            trim($this->postalCode.' '.$this->municipality),
        ], static fn (string $line): bool => $line !== ''));
    }
}

<?php

namespace App\Support;

final class CountryOptions
{
    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_map(
            static fn (array $country): string => (string) $country['code'],
            config('countries', []),
        );
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public static function selectOptions(?string $locale = null): array
    {
        $locale = self::normalizeLocale($locale ?? app()->getLocale());
        $nameKey = 'name_'.$locale;

        $countries = config('countries', []);
        usort($countries, static fn (array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return array_map(static function (array $country) use ($nameKey): array {
            $code = (string) $country['code'];
            $name = (string) ($country[$nameKey] ?? $country['name_en'] ?? $code);

            return [
                'code' => $code,
                'label' => $code.' - '.$name,
            ];
        }, $countries);
    }

    private static function normalizeLocale(string $locale): string
    {
        return in_array($locale, config('locales.supported', []), true) ? $locale : 'en';
    }
}

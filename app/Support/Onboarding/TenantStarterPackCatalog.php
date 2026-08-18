<?php

declare(strict_types=1);

namespace App\Support\Onboarding;

use App\Enums\TenantStarterPackType;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

final class TenantStarterPackCatalog
{
    /**
     * @return array{
     *     teams: array<string, array{categories: list<string>}>,
     *     categories: list<string>,
     *     units: list<array{key: string, category: string}>
     * }
     */
    public static function definition(TenantStarterPackType $type): array
    {
        $pack = config('tenant_starter_packs.'.$type->value);

        if (! is_array($pack) || $pack === []) {
            throw new InvalidArgumentException('Unknown tenant starter pack: '.$type->value);
        }

        return $pack;
    }

    public static function name(string $key, string $locale): string
    {
        $value = Lang::get($key, [], LocaleSupport::normalize($locale));

        if (! is_string($value) || $value === '' || $value === $key) {
            throw new InvalidArgumentException('Missing starter pack translation: '.$key);
        }

        return $value;
    }

    /**
     * @return array<string, string> locale => name
     */
    public static function namesByLocale(string $key): array
    {
        $names = [];

        foreach (config('locales.supported', []) as $locale) {
            if (! is_string($locale)) {
                continue;
            }
            $names[$locale] = self::name($key, $locale);
        }

        return $names;
    }

    public static function teamNameKey(TenantStarterPackType $type, string $teamKey): string
    {
        return 'starter_pack.packs.'.$type->value.'.teams.'.$teamKey;
    }

    public static function categoryNameKey(TenantStarterPackType $type, string $categoryKey): string
    {
        return 'starter_pack.packs.'.$type->value.'.categories.'.$categoryKey;
    }

    public static function locationNameKey(TenantStarterPackType $type): string
    {
        return 'starter_pack.packs.'.$type->value.'.location';
    }

    public static function unitNameKey(TenantStarterPackType $type, string $unitKey): string
    {
        return 'starter_pack.packs.'.$type->value.'.units.'.$unitKey;
    }

    /**
     * @return array{
     *     teams: list<string>,
     *     categories: list<string>,
     *     location: string,
     *     units: list<string>
     * }
     */
    public static function preview(TenantStarterPackType $type, string $locale): array
    {
        $definition = self::definition($type);
        $locale = LocaleSupport::normalize($locale);

        $teams = [];
        foreach (array_keys($definition['teams']) as $teamKey) {
            $teams[] = self::name(self::teamNameKey($type, (string) $teamKey), $locale);
        }

        $categories = [];
        foreach ($definition['categories'] as $categoryKey) {
            $categories[] = self::name(self::categoryNameKey($type, (string) $categoryKey), $locale);
        }

        $units = [];
        foreach ($definition['units'] as $unit) {
            $units[] = self::name(self::unitNameKey($type, (string) $unit['key']), $locale);
        }

        return [
            'teams' => $teams,
            'categories' => $categories,
            'location' => self::name(self::locationNameKey($type), $locale),
            'units' => $units,
        ];
    }
}

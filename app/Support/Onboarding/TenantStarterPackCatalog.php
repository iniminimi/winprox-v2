<?php

declare(strict_types=1);

namespace App\Support\Onboarding;

use App\Enums\TenantStarterPackType;
use App\Models\Tenant;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

final class TenantStarterPackCatalog
{
    /** @var array<string, string> */
    private const WORK_MENU_LABEL_KEYS = [
        'calendar' => 'settings.work_menu.calendar_label',
        'reservations' => 'settings.work_menu.reservations_label',
        'inspection_rounds' => 'settings.work_menu.inspection_rounds_label',
        'unit_measurements' => 'settings.work_menu.unit_measurements_label',
    ];
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

    /**
     * @return array{
     *     calendar: bool,
     *     reservations: bool,
     *     inspection_rounds: bool,
     *     unit_measurements: bool,
     * }
     */
    public static function workMenuFlags(TenantStarterPackType $type): array
    {
        $pack = self::definition($type);
        $menu = $pack['work_menu'] ?? 'all_work_menu_on';

        if (is_string($menu)) {
            $resolved = config('tenant_starter_packs.'.$menu);
            if (! is_array($resolved)) {
                throw new InvalidArgumentException('Unknown starter pack work_menu preset: '.$menu);
            }
            $menu = $resolved;
        }

        return [
            'calendar' => (bool) ($menu['calendar'] ?? true),
            'reservations' => (bool) ($menu['reservations'] ?? true),
            'inspection_rounds' => (bool) ($menu['inspection_rounds'] ?? true),
            'unit_measurements' => (bool) ($menu['unit_measurements'] ?? true),
        ];
    }

    /**
     * @return array{
     *     work_menu_calendar_enabled: bool,
     *     work_menu_reservations_enabled: bool,
     *     work_menu_inspection_rounds_enabled: bool,
     *     work_menu_unit_measurements_enabled: bool,
     * }
     */
    public static function workMenuDefaults(TenantStarterPackType $type): array
    {
        $flags = self::workMenuFlags($type);

        return [
            'work_menu_calendar_enabled' => $flags['calendar'],
            'work_menu_reservations_enabled' => $flags['reservations'],
            'work_menu_inspection_rounds_enabled' => $flags['inspection_rounds'],
            'work_menu_unit_measurements_enabled' => $flags['unit_measurements'],
        ];
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
     * @param  array{
     *     calendar: bool,
     *     reservations: bool,
     *     inspection_rounds: bool,
     *     unit_measurements: bool,
     * }  $flags
     * @return list<array{label: string, enabled: bool}>
     */
    public static function workMenuItems(array $flags, string $locale): array
    {
        $locale = LocaleSupport::normalize($locale);
        $items = [];

        foreach (self::WORK_MENU_LABEL_KEYS as $key => $labelKey) {
            $items[] = [
                'label' => Lang::get($labelKey, [], $locale),
                'enabled' => (bool) ($flags[$key] ?? true),
            ];
        }

        return $items;
    }

    public static function workMenuItemsForTenant(Tenant $tenant, string $locale): array
    {
        return self::workMenuItems([
            'calendar' => $tenant->workMenuCalendarEnabled(),
            'reservations' => $tenant->workMenuReservationsEnabled(),
            'inspection_rounds' => $tenant->workMenuInspectionRoundsEnabled(),
            'unit_measurements' => $tenant->workMenuUnitMeasurementsEnabled(),
        ], $locale);
    }

    /**
     * @return array{
     *     teams: list<string>,
     *     categories: list<string>,
     *     location: string,
     *     units: list<string>,
     *     work_menu: list<array{label: string, enabled: bool}>
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
            'work_menu' => self::workMenuItems(self::workMenuFlags($type), $locale),
        ];
    }
}

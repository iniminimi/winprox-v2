<?php

declare(strict_types=1);

namespace App\Support\Onboarding;

use App\Enums\TenantStarterPackSize;
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
     *     work_menu?: mixed,
     *     teams: array<string, array{categories: list<string>}>,
     *     categories: list<string>,
     *     units: list<array{key: string, category: string}>
     * }
     */
    public static function definition(TenantStarterPackType $type, ?TenantStarterPackSize $size = null): array
    {
        $pack = config('tenant_starter_packs.'.$type->value);

        if (! is_array($pack) || $pack === []) {
            throw new InvalidArgumentException('Unknown tenant starter pack: '.$type->value);
        }

        return self::applySizeOverlay($pack, self::resolvedSize($type, $size));
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
    public static function workMenuFlags(TenantStarterPackType $type, ?TenantStarterPackSize $size = null): array
    {
        $pack = self::definition($type, TenantStarterPackSize::Large);
        $menu = $pack['work_menu'] ?? 'all_work_menu_on';

        if (is_string($menu)) {
            $resolved = config('tenant_starter_packs.'.$menu);
            if (! is_array($resolved)) {
                throw new InvalidArgumentException('Unknown starter pack work_menu preset: '.$menu);
            }
            $menu = $resolved;
        }

        $flags = [
            'calendar' => (bool) ($menu['calendar'] ?? true),
            'reservations' => (bool) ($menu['reservations'] ?? true),
            'inspection_rounds' => (bool) ($menu['inspection_rounds'] ?? true),
            'unit_measurements' => (bool) ($menu['unit_measurements'] ?? true),
        ];

        return match (self::resolvedSize($type, $size)) {
            TenantStarterPackSize::Small => [
                'calendar' => false,
                'reservations' => false,
                'inspection_rounds' => false,
                'unit_measurements' => false,
            ],
            TenantStarterPackSize::Medium => [
                'calendar' => true,
                'reservations' => false,
                'inspection_rounds' => false,
                'unit_measurements' => false,
            ],
            default => $flags,
        };
    }

    /**
     * @return array{
     *     work_menu_calendar_enabled: bool,
     *     work_menu_reservations_enabled: bool,
     *     work_menu_inspection_rounds_enabled: bool,
     *     work_menu_unit_measurements_enabled: bool,
     * }
     */
    public static function workMenuDefaults(TenantStarterPackType $type, ?TenantStarterPackSize $size = null): array
    {
        $flags = self::workMenuFlags($type, $size);

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
    public static function preview(TenantStarterPackType $type, string $locale, ?TenantStarterPackSize $size = null): array
    {
        $definition = self::definition($type, $size);
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
            'work_menu' => self::workMenuItems(self::workMenuFlags($type, $size), $locale),
        ];
    }

    private static function resolvedSize(TenantStarterPackType $type, ?TenantStarterPackSize $size): ?TenantStarterPackSize
    {
        if (! $type->asksCompanySize()) {
            return null;
        }

        return $size ?? TenantStarterPackSize::Large;
    }

    /**
     * @param  array{
     *     work_menu?: mixed,
     *     teams: array<string, array{categories: list<string>}>,
     *     categories: list<string>,
     *     units: list<array{key: string, category: string}>
     * }  $pack
     * @return array{
     *     work_menu?: mixed,
     *     teams: array<string, array{categories: list<string>}>,
     *     categories: list<string>,
     *     units: list<array{key: string, category: string}>
     * }
     */
    private static function applySizeOverlay(array $pack, ?TenantStarterPackSize $size): array
    {
        $limits = match ($size) {
            TenantStarterPackSize::Small => ['teams' => 1, 'categories' => 2, 'units' => 2],
            TenantStarterPackSize::Medium => ['teams' => 1, 'categories' => 3, 'units' => 3],
            default => null,
        };

        if ($limits === null) {
            return $pack;
        }

        $categories = array_values(array_slice($pack['categories'], 0, $limits['categories']));
        $categorySet = array_fill_keys($categories, true);

        $teams = [];
        foreach ($pack['teams'] as $teamKey => $teamDef) {
            if (count($teams) >= $limits['teams']) {
                break;
            }
            $teamCategories = array_values(array_filter(
                $teamDef['categories'] ?? [],
                fn (mixed $category): bool => is_string($category) && isset($categorySet[$category]),
            ));
            $teams[(string) $teamKey] = ['categories' => $teamCategories];
        }

        $units = [];
        foreach ($pack['units'] as $unit) {
            if (count($units) >= $limits['units']) {
                break;
            }
            $category = (string) ($unit['category'] ?? '');
            if (! isset($categorySet[$category])) {
                continue;
            }
            $units[] = $unit;
        }

        $pack['teams'] = $teams;
        $pack['categories'] = $categories;
        $pack['units'] = $units;

        return $pack;
    }
}

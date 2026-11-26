<?php

declare(strict_types=1);

namespace App\Actions\Ops;

use App\Models\ClockPoint;
use App\Models\HardwareDevice;
use App\Models\Screen;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Waar een inventaris-MAC echt hangt: organisatie, locatie en unit.
 * Alleen als een klokpunt of scherm die MAC gekoppeld heeft.
 */
final class ResolveHardwareDevicePlacementsAction
{
    /**
     * @param  iterable<int, HardwareDevice>  $devices
     * @return array<int, array{parts: list<string>}>
     */
    public function handle(iterable $devices): array
    {
        $devices = Collection::wrap($devices)->values();
        if ($devices->isEmpty()) {
            return [];
        }

        $macs = $devices
            ->pluck('mac')
            ->filter(fn ($mac) => is_string($mac) && $mac !== '')
            ->unique()
            ->values();
        $pointIds = $devices
            ->pluck('clock_point_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($macs->isEmpty() && $pointIds->isEmpty()) {
            return [];
        }

        $points = ClockPoint::query()
            ->withoutGlobalScopes()
            ->with([
                'location' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->where(function ($query) use ($macs, $pointIds): void {
                if ($pointIds->isNotEmpty()) {
                    $query->whereIn('id', $pointIds->all());
                }
                if ($macs->isNotEmpty()) {
                    $pointIds->isNotEmpty()
                        ? $query->orWhereIn('display_device_hint', $macs->all())
                        : $query->whereIn('display_device_hint', $macs->all());
                }
            })
            ->get();

        $screens = $macs->isEmpty()
            ? collect()
            : Screen::query()
                ->withoutGlobalScopes()
                ->with([
                    'unit' => fn ($query) => $query->withoutGlobalScopes()->with([
                        'location' => fn ($location) => $location->withoutGlobalScopes(),
                    ]),
                ])
                ->whereIn('device_hint', $macs->all())
                ->whereNotNull('paired_at')
                ->get();

        $tenantIds = $points->pluck('tenant_id')
            ->merge($screens->pluck('tenant_id'))
            ->merge($devices->pluck('tenant_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $tenants = $tenantIds->isEmpty()
            ? collect()
            : Tenant::query()->whereIn('id', $tenantIds->all())->get()->keyBy('id');

        $placements = [];
        foreach ($devices as $device) {
            $placement = $this->placementFor($device, $points, $screens, $tenants);
            if ($placement !== null) {
                $placements[(int) $device->id] = $placement;
            }
        }

        return $placements;
    }

    /**
     * @param  Collection<int, ClockPoint>  $points
     * @param  Collection<int, Screen>  $screens
     * @param  Collection<int, Tenant>  $tenants
     * @return array{parts: list<string>}|null
     */
    private function placementFor(HardwareDevice $device, Collection $points, Collection $screens, Collection $tenants): ?array
    {
        $mac = is_string($device->mac) && $device->mac !== '' ? $device->mac : null;
        $point = $device->clock_point_id !== null
            ? $points->firstWhere('id', (int) $device->clock_point_id)
            : null;
        if ($point === null && $mac !== null) {
            $point = $points->first(fn (ClockPoint $candidate) => $candidate->display_device_hint === $mac
                && $candidate->display_paired_at !== null);
        }

        $screen = null;
        if ($mac !== null) {
            $screen = $screens->first(fn (Screen $candidate) => $candidate->device_hint === $mac && $candidate->unit_id !== null)
                ?? $screens->first(fn (Screen $candidate) => $candidate->device_hint === $mac);
        }

        if ($point === null && $screen === null) {
            return null;
        }

        $tenantId = $point?->tenant_id ?? $screen?->tenant_id ?? $device->tenant_id;
        $tenant = $tenantId !== null ? $tenants->get((int) $tenantId) : null;
        $location = $point?->location ?? $screen?->unit?->location;
        $companyName = trim((string) ($tenant?->name ?? ''));
        $locationName = trim((string) ($location?->name ?? ''));
        $unitName = trim((string) ($screen?->unit?->name ?? ''));
        $clockPointName = trim((string) ($point?->name ?? ''));
        $companyAddress = $tenant?->organisationAddressLine();
        $locationAddress = $location !== null && trim($location->formattedAddress()) !== ''
            ? $location->formattedAddress()
            : null;

        $parts = [];
        if ($companyName !== '') {
            $parts[] = __('ops.devices.placement_company').': '.$companyName
                .($companyAddress ? ' · '.$companyAddress : '');
        }
        if ($locationName !== '') {
            $parts[] = __('ops.devices.placement_location').': '.$locationName
                .($locationAddress ? ', '.$locationAddress : '');
        }
        if ($unitName !== '') {
            $parts[] = __('ops.devices.placement_unit').': '.$unitName;
        }
        if ($clockPointName !== '') {
            $parts[] = __('ops.devices.placement_clock_point').': '.$clockPointName;
        }

        return $parts === [] ? null : ['parts' => $parts];
    }
}

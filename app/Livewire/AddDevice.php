<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Cameras\ConfirmCameraClaimAction;
use App\Actions\Cameras\DenyCameraClaimAction;
use App\Actions\Cameras\IssueCameraPairingCodeAction;
use App\Actions\Screens\ConfirmScreenClaimAction;
use App\Actions\Screens\DenyScreenClaimAction;
use App\Actions\Screens\IssueScreenPairingCodeAction;
use App\Actions\Time\ConfirmClockDisplayClaimAction;
use App\Actions\Time\DenyClockDisplayClaimAction;
use App\Actions\Time\IssueClockDisplayPairingCodeAction;
use App\Models\Camera;
use App\Models\CameraClaim;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Screen;
use App\Models\ScreenClaim;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Beginscherm: een beheerder kiest het toestel en de plek, krijgt de bestaande
 * koppelcode, en bevestigt de aanvraag in hetzelfde venster.
 */
class AddDevice extends Component
{
    public bool $open = false;

    public string $deviceType = '';

    public ?int $locationId = null;

    public ?int $placeId = null;

    public ?string $flash = null;

    #[On('open-add-device')]
    public function openModal(): void
    {
        $this->authorize('pairDeviceAny', Location::class);
        $this->resetForm();
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function updatedDeviceType(): void
    {
        $this->locationId = null;
        $this->placeId = null;
        $this->flash = null;
        $this->resetValidation();
    }

    public function updatedLocationId(): void
    {
        $this->placeId = null;
        $this->flash = null;
        $this->resetValidation();
    }

    public function updatedPlaceId(): void
    {
        $this->flash = null;
        $this->resetValidation();
    }

    public function issue(
        IssueClockDisplayPairingCodeAction $clock,
        IssueScreenPairingCodeAction $screen,
        IssueCameraPairingCodeAction $camera,
    ): void {
        $this->authorize('pairDeviceAny', Location::class);
        $this->validatePlace();
        $this->flash = null;

        $tenantId = $this->tenantId();
        $actorId = auth()->id();

        try {
            match ($this->deviceType) {
                'clock' => $clock->handle($this->activeClockPoint(), $tenantId, $actorId),
                'screen' => $screen->handle($this->activeUnit(), $tenantId, $actorId),
                'camera' => $camera->handle($this->activeUnit(), $tenantId, $actorId),
                default => abort(404),
            };
        } catch (InvalidArgumentException $e) {
            $this->addError('placeId', __('time.clock_displays.errors.'.$e->getMessage()));
        }
    }

    public function confirmClaim(
        int $claimId,
        ConfirmClockDisplayClaimAction $clock,
        ConfirmScreenClaimAction $screen,
        ConfirmCameraClaimAction $camera,
    ): void {
        $this->authorize('pairDeviceAny', Location::class);
        $tenantId = $this->tenantId();
        $actorId = auth()->id();

        try {
            match ($this->deviceType) {
                'clock' => $clock->handle($this->clockClaim($claimId), $tenantId, $actorId),
                'screen' => $screen->handle($this->screenClaim($claimId), $tenantId, $actorId),
                'camera' => $camera->handle($this->cameraClaim($claimId), $tenantId, $actorId),
                default => abort(404),
            };
        } catch (InvalidArgumentException $e) {
            $this->addError('claim', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        $this->flash = __('dashboard.add_device.confirmed');
    }

    public function denyClaim(
        int $claimId,
        DenyClockDisplayClaimAction $clock,
        DenyScreenClaimAction $screen,
        DenyCameraClaimAction $camera,
    ): void {
        $this->authorize('pairDeviceAny', Location::class);
        $tenantId = $this->tenantId();
        $actorId = auth()->id();

        try {
            match ($this->deviceType) {
                'clock' => $clock->handle($this->clockClaim($claimId), $tenantId, $actorId),
                'screen' => $screen->handle($this->screenClaim($claimId), $tenantId, $actorId),
                'camera' => $camera->handle($this->cameraClaim($claimId), $tenantId, $actorId),
                default => abort(404),
            };
        } catch (InvalidArgumentException $e) {
            $this->addError('claim', __('time.clock_displays.errors.'.$e->getMessage()));

            return;
        }

        $this->flash = __('dashboard.add_device.denied');
    }

    public function render(): View
    {
        $user = auth()->user();
        $allowed = $user instanceof User && $user->can('pairDeviceAny', Location::class);
        $locations = $allowed && $this->open ? $this->locationOptions() : [];
        $places = $allowed && $this->open ? $this->placeOptions() : [];
        $code = $allowed && $this->open ? $this->openCode() : null;
        $pending = $allowed && $this->open && $this->placeId !== null ? $this->pendingClaim() : null;

        return view('livewire.add-device', [
            'allowed' => $allowed,
            'types' => $allowed ? $this->allowedTypes() : [],
            'locations' => $locations,
            'places' => $places,
            'code' => $code === null ? null : $this->formatCode($code['code']),
            'validUntil' => $code['until'] ?? null,
            'alreadyLinked' => $allowed && $this->open && $this->alreadyLinked(),
            'pending' => $pending,
        ]);
    }

    private function resetForm(): void
    {
        $this->deviceType = '';
        $this->locationId = null;
        $this->placeId = null;
        $this->flash = null;
        $this->resetValidation();
    }

    /** @return list<string> */
    private function allowedTypes(): array
    {
        $types = [];
        $user = auth()->user();
        if ($user instanceof User && $user->can('viewAny', ClockPoint::class)) {
            $types[] = 'clock';
        }
        $types[] = 'screen';
        $types[] = 'camera';

        return $types;
    }

    private function validatePlace(): void
    {
        $this->validate([
            'deviceType' => ['required', Rule::in($this->allowedTypes())],
            'locationId' => ['required', 'integer', 'min:0'],
            'placeId' => ['required', 'integer'],
        ], [], [
            'deviceType' => __('dashboard.add_device.type_label'),
            'locationId' => __('dashboard.add_device.place_location'),
            'placeId' => $this->deviceType === 'clock'
                ? __('dashboard.add_device.place_clock')
                : __('dashboard.add_device.place_unit'),
        ]);
    }

    private function tenantId(): int
    {
        $tenantId = Tenancy::id();
        abort_if($tenantId === null, 403);

        return (int) $tenantId;
    }

    private function activeClockPoint(): ClockPoint
    {
        $point = ClockPoint::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($this->placeId)
            ->where('is_active', true)
            ->when(
                $this->selectedLocationId() === null,
                fn ($query) => $query->whereNull('location_id'),
                fn ($query) => $query->where('location_id', $this->selectedLocationId()),
            )
            ->first();
        abort_if($point === null, 404);
        $this->authorize('update', $point);
        $this->authorizePlace($point->location_id !== null ? (int) $point->location_id : null);

        return $point;
    }

    private function activeUnit(): Unit
    {
        $unit = Unit::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($this->placeId)
            ->where('is_active', true)
            ->when(
                $this->selectedLocationId() === null,
                fn ($query) => $query->whereNull('location_id'),
                fn ($query) => $query->where('location_id', $this->selectedLocationId()),
            )
            ->first();
        abort_if($unit === null, 404);
        $this->authorizePlace($unit->location_id !== null ? (int) $unit->location_id : null);

        return $unit;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function locationOptions(): array
    {
        if (! in_array($this->deviceType, ['clock', 'screen', 'camera'], true)) {
            return [];
        }

        $places = $this->deviceType === 'clock'
            ? ClockPoint::query()->where('tenant_id', $this->tenantId())->where('is_active', true)
            : Unit::query()->where('tenant_id', $this->tenantId())->where('is_active', true);

        $viewer = auth()->user();
        $allowedIds = $viewer instanceof User ? $viewer->accessibleLocationIds() : [];

        $options = Location::query()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->whereIn('id', (clone $places)->whereNotNull('location_id')->select('location_id'))
            ->when(is_array($allowedIds), fn ($query) => $query->whereIn('id', $allowedIds === [] ? [0] : $allowedIds))
            ->orderBy('name')
            ->get()
            ->map(fn (Location $location): array => [
                'id' => (int) $location->id,
                'label' => $location->localizedName(),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        if ($viewer instanceof User && $viewer->hasUnrestrictedLocationAccess() && (clone $places)->whereNull('location_id')->exists()) {
            $options->push([
                'id' => 0,
                'label' => __('dashboard.add_device.no_location'),
            ]);
        }

        return $options->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function placeOptions(): array
    {
        if ($this->locationId === null || ! in_array($this->deviceType, ['clock', 'screen', 'camera'], true) || ! $this->mayListPlaces()) {
            return [];
        }

        $locationId = $this->locationId === 0 ? null : $this->locationId;

        if ($this->deviceType === 'clock') {
            return ClockPoint::query()
                ->where('tenant_id', $this->tenantId())
                ->where('is_active', true)
                ->when(
                    $locationId === null,
                    fn ($query) => $query->whereNull('location_id'),
                    fn ($query) => $query->where('location_id', $locationId),
                )
                ->orderBy('name')
                ->get()
                ->map(fn (ClockPoint $point): array => [
                    'id' => (int) $point->id,
                    'label' => (string) $point->name,
                ])
                ->all();
        }

        return Unit::query()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->when(
                $locationId === null,
                fn ($query) => $query->whereNull('location_id'),
                fn ($query) => $query->where('location_id', $locationId),
            )
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit): array => [
                'id' => (int) $unit->id,
                'label' => $unit->localizedName(),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function selectedLocationId(): ?int
    {
        abort_if($this->locationId === null, 404);

        return $this->locationId === 0 ? null : $this->locationId;
    }

    private function authorizePlace(?int $locationId): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $this->authorize('pairDeviceAny', Location::class);

        if ($locationId === null) {
            abort_unless($user->hasUnrestrictedLocationAccess(), 403);

            return;
        }

        $location = Location::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($locationId)
            ->first();
        abort_if($location === null, 404);
        $this->authorize('pairDevice', $location);
    }

    private function mayListPlaces(): bool
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($this->locationId === 0) {
            return $user->hasUnrestrictedLocationAccess();
        }

        $location = Location::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($this->locationId)
            ->first();

        return $location instanceof Location && $user->can('pairDevice', $location);
    }

    /**
     * @return array{code: string, until: string}|null
     */
    private function openCode(): ?array
    {
        if ($this->placeId === null) {
            return null;
        }

        if ($this->deviceType === 'clock') {
            $point = ClockPoint::query()
                ->where('tenant_id', $this->tenantId())
                ->whereKey($this->placeId)
                ->first();
            if ($point === null || $point->display_pairing_code === null || ! $point->display_pairing_expires_at?->isFuture()) {
                return null;
            }

            return [
                'code' => (string) $point->display_pairing_code,
                'until' => $point->display_pairing_expires_at->format('H:i'),
            ];
        }

        if ($this->deviceType === 'screen') {
            $screen = Screen::query()->where('unit_id', $this->placeId)->first();
            if ($screen === null || ! $screen->hasOpenCode()) {
                return null;
            }

            return [
                'code' => (string) $screen->pairing_code,
                'until' => $screen->pairing_expires_at?->format('H:i') ?? '',
            ];
        }

        if ($this->deviceType === 'camera') {
            $camera = Camera::query()->where('unit_id', $this->placeId)->first();
            if ($camera === null || ! $camera->hasOpenCode()) {
                return null;
            }

            return [
                'code' => (string) $camera->pairing_code,
                'until' => $camera->pairing_expires_at?->format('H:i') ?? '',
            ];
        }

        return null;
    }

    private function alreadyLinked(): bool
    {
        if ($this->placeId === null) {
            return false;
        }

        return match ($this->deviceType) {
            'clock' => (bool) ClockPoint::query()->where('tenant_id', $this->tenantId())->whereKey($this->placeId)->first()?->hasLinkedDisplay(),
            'screen' => (bool) Screen::query()->where('unit_id', $this->placeId)->first()?->isLinked(),
            'camera' => (bool) Camera::query()->where('unit_id', $this->placeId)->first()?->isLinked(),
            default => false,
        };
    }

    private function pendingClaim(): ClockDisplayClaim|ScreenClaim|CameraClaim|null
    {
        return match ($this->deviceType) {
            'clock' => ClockPoint::query()->where('tenant_id', $this->tenantId())->whereKey($this->placeId)->first()
                ?->displayClaims()->activePending()->latest('id')->first(),
            'screen' => Screen::query()->where('unit_id', $this->placeId)->first()
                ?->claims()->activePending()->latest('id')->first(),
            'camera' => Camera::query()->where('unit_id', $this->placeId)->first()
                ?->claims()->activePending()->latest('id')->first(),
            default => null,
        };
    }

    private function clockClaim(int $claimId): ClockDisplayClaim
    {
        $point = $this->activeClockPoint();
        $claim = ClockDisplayClaim::query()
            ->whereKey($claimId)
            ->where('tenant_id', $point->tenant_id)
            ->where('clock_point_id', $point->id)
            ->first();
        abort_if($claim === null, 404);

        return $claim;
    }

    private function screenClaim(int $claimId): ScreenClaim
    {
        $unit = $this->activeUnit();
        $claim = ScreenClaim::query()->whereKey($claimId)->first();
        $screen = $claim?->screen;
        abort_if($claim === null || $screen === null || (int) $screen->unit_id !== (int) $unit->id, 404);

        return $claim;
    }

    private function cameraClaim(int $claimId): CameraClaim
    {
        $unit = $this->activeUnit();
        $claim = CameraClaim::query()->whereKey($claimId)->first();
        $camera = $claim?->camera;
        abort_if($claim === null || $camera === null || (int) $camera->unit_id !== (int) $unit->id, 404);

        return $claim;
    }

    private function formatCode(string $code): string
    {
        if (strlen($code) !== 8) {
            return $code;
        }

        return substr($code, 0, 4).'-'.substr($code, 4);
    }
}

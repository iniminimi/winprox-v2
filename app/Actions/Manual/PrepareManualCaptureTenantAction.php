<?php

declare(strict_types=1);

namespace App\Actions\Manual;

use App\Actions\TenantPurge\CancelOpenExpiredTrialPurgesForTenantAction;
use App\Actions\Time\ClearWorkerClockDeviceAction;
use App\Actions\Time\ClockOutAction;
use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Actions\Time\FindOpenWorkShiftForWorkerAction;
use App\Actions\Units\RecordUnitGpsReportAction;
use App\Data\Units\RecordUnitGpsReportData;
use App\Enums\ClockSource;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitGpsReport;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class PrepareManualCaptureTenantAction
{
    public function __construct(
        private EnsureDefaultClockPointAction $ensureDefaultClockPoint,
        private CancelOpenExpiredTrialPurgesForTenantAction $cancelExpiredTrialPurges,
        private ClearWorkerClockDeviceAction $clearClockDevice,
    ) {}

    public function handle(?string $email = null, bool $checkmate = false): Tenant
    {
        $email = trim((string) ($email ?? ($checkmate
            ? config('manual_capture.checkmate_email')
            : config('manual_capture.email'))));

        if ($email === '') {
            throw new InvalidArgumentException('manual_capture_not_configured');
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new InvalidArgumentException('manual_capture_user_not_found');
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            throw new InvalidArgumentException('manual_capture_user_has_no_tenant');
        }

        // MustVerifyEmail: na login anders redirect naar /email/verify → geen shots.
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $updates = [];

        // Verlopen trial → redirect naar /subscription; capture-selectors verschijnen nooit.
        if (! $tenant->hasFullAppAccess()) {
            $updates['trial_ends_at'] = now()->addDays(max(1, (int) config('billing.trial_days', 14)));
            $updates['is_active'] = true;
        }

        if ($checkmate) {
            // Checkmate-screenshots tonen de Checkmate-UI (whitelist-nav, GPS-bezoeken).
            if (! $tenant->checkmateMode()) {
                $updates['checkmate_mode'] = true;
            }
            if (! $tenant->customersOnLocation()) {
                $updates['customers_on_location'] = true;
            }
        } elseif (! $tenant->hasEsgModule()) {
            $updates['has_esg_module'] = true;
        }

        if (! $checkmate && ! $tenant->hasTimeModule()) {
            $updates['has_time_module'] = true;
        }

        if (! $tenant->allowsGpsWorkVisits()) {
            if (! $checkmate) {
                $updates['has_time_module'] = true;
            }
            $updates['time_gps_visits'] = true;
        }

        if (! $checkmate && ! $tenant->hasIotModule()) {
            $updates['has_iot_module'] = true;
        }

        if ($updates !== []) {
            $tenant->update($updates);
            $tenant->refresh();
        }

        $this->cancelExpiredTrialPurges->handle($tenant, $user);

        $this->ensureDefaultClockPoint->handle(
            $tenant,
            __('team.clock_point_qr.default_name'),
            $user->id,
        );

        $this->prepareCaptureWorker($tenant, $user, $checkmate);

        if (! $checkmate) {
            $this->ensureCaptureLocationGpsPin($tenant, $user);
        }

        return $tenant->refresh();
    }

    /**
     * De locatiepagina toont 20 units op naam. Zonder pin op die eerste
     * pagina slaat de capture `locations-gps-history` over.
     */
    private function ensureCaptureLocationGpsPin(Tenant $tenant, User $user): void
    {
        $locationId = (int) config('manual_capture.location_id');
        if ($locationId <= 0) {
            return;
        }

        $location = Location::query()
            ->where('tenant_id', $tenant->id)
            ->find($locationId);

        if ($location === null) {
            return;
        }

        $firstPageIds = Unit::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->orderBy('name')
            ->limit(20)
            ->pluck('id');

        if ($firstPageIds->isEmpty()) {
            return;
        }

        $hasPin = UnitGpsReport::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('unit_id', $firstPageIds)
            ->exists();

        if ($hasPin) {
            return;
        }

        $unit = Unit::query()->find($firstPageIds->first());
        if ($unit === null) {
            return;
        }

        $latitude = (float) (config('manual_capture.geo_latitude') ?: 51.0289);
        $longitude = (float) (config('manual_capture.geo_longitude') ?: 4.4803);

        app(RecordUnitGpsReportAction::class)->handle(
            $unit,
            new RecordUnitGpsReportData($latitude, $longitude, CarbonImmutable::now()),
            (int) $tenant->id,
            (int) $user->id,
        );
    }

    /**
     * Capture-worker: toestel vrijgeven, teamleader-flag, en (facility) een
     * collega met geblokkeerd icoon voor de release-screenshot.
     */
    private function prepareCaptureWorker(Tenant $tenant, User $user, bool $checkmate): void
    {
        $prefix = $checkmate ? 'checkmate_worker' : 'worker';
        $first = trim((string) config("manual_capture.{$prefix}_first_name"));
        $last = trim((string) config("manual_capture.{$prefix}_last_name"));

        if ($first === '' || $last === '') {
            return;
        }

        $worker = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->where('first_name', $first)
            ->where('last_name', $last)
            ->first();

        if ($worker === null) {
            return;
        }

        // Een vorige capture kan de uitvoerder ingeklokt achterlaten. Het
        // bezoek-shot klokt dan uit in plaats van in, en de volgende taal faalt.
        $this->closeOpenCaptureShift($worker, $tenant);

        // De capture-browser is per run een "nieuw toestel"; Time koppelt max.
        // één gsm per uitvoerder. Zonder vrijgave weigert elke verse sessie.
        $this->clearClockDevice->handle($worker, (int) $tenant->id, (int) $user->id);

        if (! $checkmate) {
            $this->ensureFacilityTeamleaderCaptureState($worker);
        }
    }

    /**
     * Teamleader-shots: tile + release/workers-paneel vereisen is_teamleader;
     * release-shot vereist een tweede worker met field_icon_locked_at.
     */
    private function ensureFacilityTeamleaderCaptureState(Worker $worker): void
    {
        $updates = [];
        if (! $worker->is_teamleader) {
            $updates['is_teamleader'] = true;
        }
        if (! $worker->is_active) {
            $updates['is_active'] = true;
        }
        if ($updates !== []) {
            $worker->forceFill($updates)->save();
        }

        $teamId = $worker->internal_team_id !== null ? (int) $worker->internal_team_id : null;
        if ($teamId === null) {
            return;
        }

        $blockedExists = Worker::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('internal_team_id', $teamId)
            ->where('id', '!=', $worker->id)
            ->whereNotNull('field_icon_locked_at')
            ->where('is_active', true)
            ->exists();

        if ($blockedExists) {
            return;
        }

        $icon = trim((string) config('manual_capture.worker_icon'));
        $blockedIcon = ($icon !== '' && $icon !== 'heart') ? 'heart' : 'star';

        Worker::query()->create([
            'tenant_id' => $worker->tenant_id,
            'internal_team_id' => $teamId,
            'first_name' => 'Capture',
            'last_name' => 'Blocked',
            'field_icon_slug' => $blockedIcon,
            'field_icon_locked_at' => now(),
            'is_active' => true,
            'is_teamleader' => false,
        ]);
    }

    private function closeOpenCaptureShift(Worker $worker, Tenant $tenant): void
    {
        if (! $tenant->hasTimeModule()) {
            return;
        }

        $open = app(FindOpenWorkShiftForWorkerAction::class)->handle($worker);
        if ($open === null) {
            return;
        }

        $clockPoint = ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if ($clockPoint === null) {
            return;
        }

        app(ClockOutAction::class)->handle(
            $worker,
            $clockPoint,
            source: ClockSource::Backoffice,
        );
    }

    public function clockPointQrToken(Tenant $tenant): ?string
    {
        return ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('qr_token');
    }
}

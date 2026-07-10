<?php

namespace App\Support\Portal;

use App\Actions\Portal\AttachWorkerDeviceAction;
use App\Actions\Portal\RegisterWorkerForPortalAction;
use App\Actions\Portal\ResolveWorkerIdentityAction;
use App\Actions\Portal\ResolveWorkerIdentityForTenantAction;
use App\Actions\Portal\RevokeWorkerDeviceSessionAction;
use App\Actions\Portal\TouchWorkerDeviceAction;
use App\Models\InternalTeam;
use App\Models\Unit;
use App\Models\Worker;
use App\Models\WorkerDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Onthoudt een veldworker per toestel (cookie ↔ worker_devices-rij, ~1 jaar) en
 * doet de naam-opzoeking op een team. Bedoeld voor gedeelde telefoons op de
 * werkvloer. Eén worker hoort bij precies één team (internal_team_id).
 *
 * Tenant-context wordt door de Livewire-componenten gezet via Tenancy::actAs()
 * in booted(); device-tokens worden defensief buiten de tenant-scope opgezocht.
 */
final class WorkerDeviceSession
{
    public const DEVICE_TOKEN_COOKIE = 'winprox_device_token';

    public static function persistDeviceToken(string $deviceToken, ?InternalTeam $team = null): void
    {
        $token = trim($deviceToken);
        if ($token === '') {
            return;
        }

        // Calculate cookie duration based on team configuration
        $durationMinutes = 60 * 24 * 365; // Default: 1 year
        if ($team !== null && $team->session_lifespan_hours !== null) {
            $durationMinutes = $team->session_lifespan_hours * 60;
        } else {
            // Fallback to 14 hours if no team configuration
            $durationMinutes = 14 * 60;
        }

        Cookie::queue(cookie(
            self::DEVICE_TOKEN_COOKIE,
            $token,
            $durationMinutes,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'lax'
        ));
    }

    public static function clearDeviceTokenCookie(): void
    {
        Cookie::queue(cookie()->forget(self::DEVICE_TOKEN_COOKIE));
    }

    public static function deviceTokenFromRequest(?Request $request = null): string
    {
        $request ??= request();

        return trim((string) $request->cookie(self::DEVICE_TOKEN_COOKIE, ''));
    }

    /**
     * Worker gekoppeld aan dit toestel (optioneel beperkt tot een team).
     */
    public static function resolveWorker(?string $deviceToken, ?InternalTeam $team = null): ?Worker
    {
        $token = trim((string) $deviceToken);
        if ($token === '') {
            return null;
        }

        $device = WorkerDevice::withoutGlobalScope('tenant')
            ->with('worker')
            ->where('device_token', $token)
            ->first();

        if ($device === null) {
            return null;
        }

        $worker = $device->worker;
        if ($worker === null || ! $worker->is_active) {
            return null;
        }

        app(TouchWorkerDeviceAction::class)->handle($device);

        if ($team !== null && (int) $worker->internal_team_id !== (int) $team->id) {
            return null;
        }

        // Check tenant boundary - worker must belong to current tenant context
        $currentTenantId = \App\Support\Tenancy::id();
        if ($currentTenantId !== null && (int) $worker->tenant_id !== $currentTenantId) {
            return null;
        }

        return $worker;
    }

    public static function workerOnTeamFromDeviceCookie(InternalTeam $team): ?Worker
    {
        return self::resolveWorker(self::deviceTokenFromRequest(), $team);
    }

    public static function workerFromDeviceCookie(): ?Worker
    {
        return self::resolveWorker(self::deviceTokenFromRequest());
    }

    public static function rememberedWorkerSessionKey(int $teamId): string
    {
        return 'wp_portal_remembered_worker_'.$teamId;
    }

    /**
     * Worker die aan deze browser hangt voor welkom + icooncheck (cookie, met sessie-fallback).
     */
    public static function rememberedWorkerOnTeam(InternalTeam $team): ?Worker
    {
        $fromCookie = self::workerOnTeamFromDeviceCookie($team);
        if ($fromCookie !== null) {
            self::bindRememberedWorker($team, $fromCookie);

            return $fromCookie;
        }

        $workerId = (int) session(self::rememberedWorkerSessionKey((int) $team->id), 0);
        if ($workerId === 0) {
            return null;
        }

        $worker = Worker::where('internal_team_id', $team->id)
            ->where('is_active', true)
            ->whereNotNull('field_icon_slug')
            ->where('field_icon_slug', '!=', '')
            ->find($workerId);

        if ($worker === null) {
            self::clearRememberedWorkerForTeam((int) $team->id);

            return null;
        }

        self::restoreDeviceCookieForWorker($worker, $team);

        return $worker;
    }

    public static function bindRememberedWorker(InternalTeam $team, Worker $worker): void
    {
        if ((int) $worker->internal_team_id !== (int) $team->id) {
            return;
        }

        session([self::rememberedWorkerSessionKey((int) $team->id) => (int) $worker->id]);
        self::restoreDeviceCookieForWorker($worker, $team);
    }

    public static function clearRememberedWorkerForTeam(int $teamId): void
    {
        session()->forget(self::rememberedWorkerSessionKey($teamId));
    }

    /**
     * Naam-opzoeking op het team (identificatiestap).
     *
     * @return array{status: 'found'|'claimable'|'not_found'|'ambiguous', worker?: Worker}
     */
    public static function resolveIdentityOnTeam(InternalTeam $team, string $firstName, string $lastName): array
    {
        $result = app(ResolveWorkerIdentityAction::class)->handle($team, $firstName, $lastName);

        return array_filter([
            'status' => $result['status']->value,
            'worker' => $result['worker'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Naam-opzoeking over alle actieve workers van de tenant (Time-portaal).
     *
     * @return array{status: 'found'|'claimable'|'not_found'|'ambiguous', worker?: Worker}
     */
    public static function resolveIdentityForTenant(int $tenantId, string $firstName, string $lastName): array
    {
        $result = app(ResolveWorkerIdentityForTenantAction::class)->handle($tenantId, $firstName, $lastName);

        return array_filter([
            'status' => $result['status']->value,
            'worker' => $result['worker'] ?? null,
        ], fn ($value) => $value !== null);
    }

    public static function bindRememberedWorkerForTenant(Worker $worker): void
    {
        $team = $worker->team;
        if ($team === null) {
            return;
        }

        self::bindRememberedWorker($team, $worker);
    }

    /**
     * Koppel deze browser los van de onthouden worker ("aanmelden als andere medewerker").
     */
    public static function revokeDeviceSessionFromRequest(?InternalTeam $team = null): void
    {
        $token = self::deviceTokenFromRequest();
        app(RevokeWorkerDeviceSessionAction::class)->handle($token);

        self::clearDeviceTokenCookie();

        if ($team instanceof InternalTeam) {
            self::clearRememberedWorkerForTeam((int) $team->id);
        }
    }

    public static function workerCanActOnUnit(Worker $worker, Unit $unit): bool
    {
        return (int) $worker->tenant_id === (int) $unit->tenant_id;
    }

    /**
     * Registreer (open registratie / onboarding) een worker op een team met een icoon.
     * Een bestaande "claimable" worker (zelfde naam, nog geen icoon) krijgt het icoon.
     *
     * @return array{worker: Worker, device_token: string}
     */
    public static function registerWorkerForTeam(
        InternalTeam $team,
        string $firstName,
        string $lastName,
        string $iconSlug,
    ): array {
        $result = app(RegisterWorkerForPortalAction::class)->handle($team, $firstName, $lastName, $iconSlug);

        self::persistDeviceToken($result['device_token'], $team);

        return $result;
    }

    private static function restoreDeviceCookieForWorker(Worker $worker, ?InternalTeam $team = null): void
    {
        $existingToken = self::deviceTokenFromRequest();

        if ($existingToken !== '') {
            return;
        }

        $device = $worker->devices()->orderByDesc('last_seen_at')->first();
        if ($device === null) {
            $result = app(AttachWorkerDeviceAction::class)->handle($worker);
            self::persistDeviceToken($result['device_token'], $team);

            return;
        }

        self::persistDeviceToken($device->device_token, $team);
    }
}

<?php

namespace App\Support\Portal;

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

    public static function persistDeviceToken(string $deviceToken): void
    {
        $token = trim($deviceToken);
        if ($token === '') {
            return;
        }

        Cookie::queue(cookie(
            self::DEVICE_TOKEN_COOKIE,
            $token,
            60 * 24 * 365,
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

        $device->forceFill(['last_seen_at' => now()])->save();

        if ($team !== null && (int) $worker->internal_team_id !== (int) $team->id) {
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

        self::restoreDeviceCookieForWorker($worker);

        return $worker;
    }

    public static function bindRememberedWorker(InternalTeam $team, Worker $worker): void
    {
        if ((int) $worker->internal_team_id !== (int) $team->id) {
            return;
        }

        session([self::rememberedWorkerSessionKey((int) $team->id) => (int) $worker->id]);
        self::restoreDeviceCookieForWorker($worker);
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
        $first = mb_strtolower(trim($firstName));
        $last = mb_strtolower(trim($lastName));
        if ($first === '' || $last === '') {
            return ['status' => 'not_found'];
        }

        $matches = Worker::where('internal_team_id', $team->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Worker $worker) => mb_strtolower(trim((string) $worker->first_name)) === $first
                && mb_strtolower(trim((string) $worker->last_name)) === $last)
            ->values();

        if ($matches->count() === 0) {
            return ['status' => 'not_found'];
        }

        if ($matches->count() > 1) {
            return ['status' => 'ambiguous'];
        }

        $worker = $matches->first();

        $iconSlug = trim((string) $worker->field_icon_slug);
        if ($iconSlug === '' || ! WorkerIcon::isValidSlug($iconSlug)) {
            return ['status' => 'claimable', 'worker' => $worker];
        }

        return ['status' => 'found', 'worker' => $worker];
    }

    /**
     * Koppel deze browser los van de onthouden worker ("aanmelden als andere medewerker").
     */
    public static function revokeDeviceSessionFromRequest(?InternalTeam $team = null): void
    {
        $token = self::deviceTokenFromRequest();
        if ($token !== '') {
            WorkerDevice::withoutGlobalScope('tenant')->where('device_token', $token)->delete();
        }

        self::clearDeviceTokenCookie();

        if ($team instanceof InternalTeam) {
            self::clearRememberedWorkerForTeam((int) $team->id);
        }
    }

    public static function workerCanActOnUnit(Worker $worker, Unit $unit): bool
    {
        $teamId = $unit->default_internal_team_id;
        if ($teamId === null) {
            return false;
        }

        return (int) $worker->internal_team_id === (int) $teamId;
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
        $iconSlug = trim($iconSlug);
        if (! WorkerIcon::isValidSlug($iconSlug)) {
            throw new \InvalidArgumentException('Invalid worker icon slug.');
        }

        $claimable = self::findClaimableWorkerOnTeam($team, $firstName, $lastName);
        if ($claimable !== null) {
            $claimable->forceFill(['field_icon_slug' => $iconSlug])->save();

            return self::attachDeviceSession($claimable);
        }

        $worker = Worker::create([
            'internal_team_id' => $team->id,
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'field_icon_slug' => $iconSlug,
            'is_active' => true,
        ]);

        return self::attachDeviceSession($worker);
    }

    private static function findClaimableWorkerOnTeam(InternalTeam $team, string $firstName, string $lastName): ?Worker
    {
        $first = mb_strtolower(trim($firstName));
        $last = mb_strtolower(trim($lastName));
        if ($first === '' || $last === '') {
            return null;
        }

        $matches = Worker::where('internal_team_id', $team->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('field_icon_slug')->orWhere('field_icon_slug', ''))
            ->get()
            ->filter(fn (Worker $worker) => mb_strtolower(trim((string) $worker->first_name)) === $first
                && mb_strtolower(trim((string) $worker->last_name)) === $last);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private static function restoreDeviceCookieForWorker(Worker $worker): void
    {
        if (self::deviceTokenFromRequest() !== '') {
            return;
        }

        $device = $worker->devices()->orderByDesc('last_seen_at')->first();
        if ($device === null) {
            return;
        }

        self::persistDeviceToken($device->device_token);
    }

    /**
     * @return array{worker: Worker, device_token: string}
     */
    private static function attachDeviceSession(Worker $worker): array
    {
        $device = WorkerDevice::create([
            'tenant_id' => $worker->tenant_id,
            'worker_id' => $worker->id,
            'device_token' => WorkerDevice::generateToken(),
            'last_seen_at' => now(),
        ]);

        self::persistDeviceToken($device->device_token);

        return ['worker' => $worker, 'device_token' => $device->device_token];
    }
}

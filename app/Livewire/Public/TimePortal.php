<?php

namespace App\Livewire\Public;

use App\Actions\Time\ClockInAction;
use App\Actions\Time\ClockOutAction;
use App\Actions\Time\EndWorkBreakAction;
use App\Actions\Time\FindOpenWorkShiftForWorkerAction;
use App\Actions\Time\LogBlockedClockPointQrAttemptAction;
use App\Actions\Time\ResolveClockPointPortalTokenAction;
use App\Livewire\Concerns\PortalTeamleaderRelease;
use App\Livewire\Concerns\SwitchesPortalUiTheme;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Portal\TimePortalData;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerIcon;
use App\Support\Portal\WorkerIconGuard;
use App\Support\Portal\WorkerVerification;
use App\Support\ResolveAppLocale;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;
use App\Support\Time\ClockPointPortalTokenResolution;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Time-portaal (Clock Point QR): in-/uitklokken, pauze en read-only takenoverzicht.
 */
#[Layout('components.layouts.public')]
#[Title('WinProx')]
class TimePortal extends Component
{
    use PortalTeamleaderRelease;
    use SwitchesPortalUiTheme;

    public string $token;
    public int $clockPointId;
    public int $tenantId;
    public string $clockPointName = '';

    public string $locale = 'nl';
    public ?string $inactiveReasonKey = null;

    public string $first_name = '';
    public string $last_name = '';
    public string $sign_in_icon_slug = '';
    public string $flashMessage = '';

    public function mount(string $token): void
    {
        $resolution = app(ResolveClockPointPortalTokenAction::class)->handle($token);

        if ($resolution->status === ClockPointPortalTokenResolution::STATUS_NOT_FOUND) {
            abort(404);
        }

        $clockPoint = $resolution->clockPoint;
        abort_unless($clockPoint, 404);

        if ($resolution->status === ClockPointPortalTokenResolution::STATUS_BLOCKED) {
            Tenancy::actAs($clockPoint->tenant_id);
            app(LogBlockedClockPointQrAttemptAction::class)->handle(
                $clockPoint,
                $token,
                $resolution->historyToken,
            );
            $this->token = $token;
            $this->clockPointId = $clockPoint->id;
            $this->tenantId = $clockPoint->tenant_id;
            $this->clockPointName = $clockPoint->name;
            $this->inactiveReasonKey = 'portal.inactive.clock_point_qr_expired';
            $this->syncLocaleFromRequest();

            return;
        }

        $this->token = $token;
        $this->clockPointId = $clockPoint->id;
        $this->tenantId = $clockPoint->tenant_id;
        $this->clockPointName = $clockPoint->name;

        Tenancy::actAs($this->tenantId);

        $this->inactiveReasonKey = TimePortalData::clockPointInactiveReasonKey($clockPoint);

        $deviceWorker = WorkerDeviceSession::workerFromDeviceCookie();
        if ($deviceWorker && (int) $deviceWorker->tenant_id === $this->tenantId) {
            $team = $deviceWorker->team;
            if ($team !== null) {
                WorkerVerification::markVerified($team, $deviceWorker);
            }
        }

        $this->syncLocaleFromRequest();
    }

    public function booted(): void
    {
        Tenancy::actAs($this->tenantId);
        app()->setLocale($this->locale);
    }

    public function switchLocale(string $locale): void
    {
        if (! in_array($locale, config('locales.supported', []), true)) {
            return;
        }

        session(['locale' => $locale]);
        Cookie::queue(ResolveAppLocale::COOKIE_NAME, $locale, ResolveAppLocale::COOKIE_MINUTES);
        $this->locale = $locale;
        app()->setLocale($this->locale);
    }

    public function identifyWorker(): void
    {
        if ($this->activeClockPoint() === null) {
            return;
        }

        $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
        ], [
            'first_name.required' => __('portal.worker.errors.name_required'),
            'last_name.required' => __('portal.worker.errors.name_required'),
        ]);

        $identity = WorkerDeviceSession::resolveIdentityForTenant($this->tenantId, $this->first_name, $this->last_name);

        if ($identity['status'] === 'ambiguous') {
            $this->addError('identify', __('portal.worker.errors.identify_ambiguous'));
            return;
        }

        if ($identity['status'] === 'not_found') {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));
            return;
        }

        if ($identity['status'] === 'claimable') {
            $this->addError('identify', __('time.portal.errors.icon_not_configured'));
            return;
        }

        $worker = $identity['worker'] ?? null;
        if ($worker === null) {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));
            return;
        }

        WorkerDeviceSession::bindRememberedWorkerForTenant($worker);
        $this->sign_in_icon_slug = '';
        $this->resetErrorBag(['identify', 'sign_in_icon_slug']);
    }

    public function signInAsDifferentWorker(): void
    {
        $verified = $this->verifiedWorker();
        $team = $verified?->team;

        WorkerDeviceSession::revokeDeviceSessionFromRequest($team);
        if ($team !== null) {
            WorkerIconGuard::clearSessionForTeam((int) $team->id);
            WorkerVerification::clearForTeam((int) $team->id);
        }

        $this->reset(['first_name', 'last_name', 'sign_in_icon_slug']);
        $this->resetErrorBag(['identify', 'sign_in_icon_slug']);
    }

    public function signInWithIcon(): void
    {
        if ($this->activeClockPoint() === null) {
            return;
        }

        $deviceWorker = $this->rememberedWorkerForTenant();
        if ($deviceWorker === null) {
            return;
        }

        $team = $deviceWorker->team;
        if ($team === null) {
            return;
        }

        if (WorkerIconGuard::isBlocked($team)) {
            $this->sign_in_icon_slug = '';
            $this->addError('sign_in_icon_slug', __('portal.worker.errors.blocked'));
            return;
        }

        $this->validate(
            ['sign_in_icon_slug' => ['required', 'string', Rule::in(WorkerIcon::SLUGS)]],
            ['sign_in_icon_slug.required' => __('portal.worker.errors.icon_required'), 'sign_in_icon_slug.in' => __('portal.worker.errors.icon_required')],
        );

        $worker = WorkerVerification::confirmIconForWorker($team, $deviceWorker, $this->sign_in_icon_slug);

        if ($worker === null) {
            WorkerIconGuard::recordFailedAttempt($team);
            $this->sign_in_icon_slug = '';
            $this->addFailedSignInError($team);
            return;
        }

        WorkerDeviceSession::bindRememberedWorkerForTenant($worker);
        $this->sign_in_icon_slug = '';
        $this->flashMessage = __('portal.worker.signed_in');
    }

    public function clockIn(ClockInAction $clockIn): void
    {
        $worker = $this->authorizedWorker();
        $clockPoint = $this->activeClockPoint();
        if ($worker === null || $clockPoint === null) {
            return;
        }

        try {
            $clockIn->handle($worker, $clockPoint, $this->deviceForWorker($worker));
            $this->flashMessage = __('time.portal.clocked_in');
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'shift_already_open') {
                $this->flashMessage = __('time.portal.errors.already_clocked_in');
            }
        }
    }

    public function clockOut(ClockOutAction $clockOut): void
    {
        $worker = $this->authorizedWorker();
        $clockPoint = $this->activeClockPoint();
        if ($worker === null || $clockPoint === null) {
            return;
        }

        try {
            $clockOut->handle($worker, $clockPoint);
            $this->flashMessage = __('time.portal.clocked_out');
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'shift_not_open') {
                $this->flashMessage = __('time.portal.errors.not_clocked_in');
            }
        }
    }

    public function startBreak(StartWorkBreakAction $startBreak, FindOpenWorkShiftForWorkerAction $findShift): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null) {
            return;
        }

        $shift = $findShift->handle($worker);
        if ($shift === null) {
            $this->flashMessage = __('time.portal.errors.not_clocked_in');
            return;
        }

        try {
            $startBreak->handle($worker, $shift);
            $this->flashMessage = __('time.portal.break_started');
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'break_already_open') {
                $this->flashMessage = __('time.portal.errors.break_already_open');
            }
        }
    }

    public function endBreak(EndWorkBreakAction $endBreak, FindOpenWorkShiftForWorkerAction $findShift): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null) {
            return;
        }

        $shift = $findShift->handle($worker);
        if ($shift === null) {
            $this->flashMessage = __('time.portal.errors.not_clocked_in');
            return;
        }

        try {
            $endBreak->handle($worker, $shift);
            $this->flashMessage = __('time.portal.break_ended');
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'break_not_open') {
                $this->flashMessage = __('time.portal.errors.break_not_open');
            }
        }
    }

    public function render(FindOpenWorkShiftForWorkerAction $findShift)
    {
        app()->setLocale($this->locale);

        $verifiedWorker = $this->verifiedWorker();
        $canAct = $verifiedWorker !== null;
        $team = $verifiedWorker?->team;

        $hasSignInWorkers = Worker::query()
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->whereNotNull('field_icon_slug')
            ->where('field_icon_slug', '!=', '')
            ->exists();

        $deviceWorker = null;
        if (! $canAct && $hasSignInWorkers) {
            $deviceWorker = $this->rememberedWorkerForTenant();
        }

        $iconBlocked = false;
        if (! $canAct && $hasSignInWorkers && $deviceWorker?->team !== null) {
            $iconBlocked = WorkerIconGuard::isBlocked($deviceWorker->team);
        }

        $showIdentify = $this->activeClockPoint() !== null && ! $canAct && $hasSignInWorkers
            && $deviceWorker === null && ! $iconBlocked;
        $showVerify = $this->activeClockPoint() !== null && ! $canAct && $hasSignInWorkers
            && $deviceWorker !== null && ! $iconBlocked;

        $openShift = null;
        if ($canAct && $verifiedWorker !== null && TimeModuleAccess::tenantHasModule(Tenant::query()->find($this->tenantId))) {
            $openShift = $findShift->handle($verifiedWorker);
        }
        $tasks = $canAct && $verifiedWorker !== null ? TimePortalData::openTasksForWorker($verifiedWorker) : collect();
        $hasTimeModule = TimeModuleAccess::tenantHasModule(Tenant::query()->find($this->tenantId));

        return view('livewire.public.time-portal', [
            'canAct' => $canAct,
            'verifiedWorker' => $verifiedWorker,
            'hasSignInWorkers' => $hasSignInWorkers,
            'showIdentify' => $showIdentify,
            'showVerify' => $showVerify,
            'iconBlocked' => $iconBlocked,
            'deviceWorker' => $deviceWorker,
            'remainingAttempts' => $deviceWorker?->team !== null
                ? WorkerIconGuard::remainingAttempts($deviceWorker->team)
                : WorkerIconGuard::MAX_FAILED_ATTEMPTS,
            'openShift' => $openShift,
            'tasks' => $tasks,
            'hasTimeModule' => $hasTimeModule,
            'isTimePortal' => true,
            'isTeamPortal' => false,
        ]);
    }

    private function activeClockPoint(): ?ClockPoint
    {
        if ($this->inactiveReasonKey !== null) {
            return null;
        }

        return ClockPoint::find($this->clockPointId);
    }

    private function verifiedWorker(): ?Worker
    {
        $deviceWorker = $this->rememberedWorkerForTenant();
        if ($deviceWorker === null) {
            return null;
        }

        $team = $deviceWorker->team;
        if ($team === null) {
            return null;
        }

        return WorkerVerification::verifiedWorker($team);
    }

    private function rememberedWorkerForTenant(): ?Worker
    {
        $fromCookie = WorkerDeviceSession::workerFromDeviceCookie();
        if ($fromCookie !== null && (int) $fromCookie->tenant_id === $this->tenantId) {
            return $fromCookie;
        }

        return Worker::query()
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->get()
            ->first(function (Worker $worker) {
                $team = $worker->team;
                if ($team === null) {
                    return false;
                }

                $remembered = WorkerDeviceSession::rememberedWorkerOnTeam($team);

                return $remembered !== null && (int) $remembered->id === (int) $worker->id;
            });
    }

    private function authorizedWorker(): ?Worker
    {
        return $this->verifiedWorker();
    }

    private function deviceForWorker(Worker $worker): ?\App\Models\WorkerDevice
    {
        $token = WorkerDeviceSession::deviceTokenFromRequest();
        if ($token === '') {
            return null;
        }

        return $worker->devices()->where('device_token', $token)->first();
    }

    private function addFailedSignInError(\App\Models\InternalTeam $team): void
    {
        if (WorkerIconGuard::isBlocked($team)) {
            $this->addError('sign_in_icon_slug', __('portal.worker.errors.blocked'));
            return;
        }
        $this->addError('sign_in_icon_slug', __('portal.worker.errors.icon_wrong'));
    }

    private function syncLocaleFromRequest(): void
    {
        $supported = config('locales.supported', []);
        $lang = request()->query('lang');

        if (is_string($lang) && in_array($lang, $supported, true)) {
            session(['locale' => $lang]);
            Cookie::queue(ResolveAppLocale::COOKIE_NAME, $lang, ResolveAppLocale::COOKIE_MINUTES);
            $this->locale = $lang;
        } else {
            $this->locale = ResolveAppLocale::resolve(request());
        }

        app()->setLocale($this->locale);
    }

    protected function portalTeamleaderWorker(): ?Worker
    {
        $worker = $this->verifiedWorker();
        return ($worker !== null && $worker->is_teamleader) ? $worker : null;
    }

    protected function portalReleaseTeam(): ?\App\Models\InternalTeam
    {
        return $this->verifiedWorker()?->team;
    }

    protected function portalReleaseFlash(string $message): void
    {
        $this->flashMessage = $message;
    }
}

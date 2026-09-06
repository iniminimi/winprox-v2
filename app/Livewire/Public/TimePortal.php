<?php

namespace App\Livewire\Public;

use App\Actions\Portal\ClearWorkerTaskBaselineAction;
use App\Actions\Portal\SyncWorkerOpenTaskBaselineAction;
use App\Actions\Time\AcknowledgeTimeRosterViewAction;
use App\Actions\Time\AssertWorkerClockDeviceAction;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\ClockOutAction;
use App\Actions\Time\ConfirmWorkerClockPinAction;
use App\Actions\Time\EndWorkBreakAction;
use App\Actions\Time\FindOpenWorkShiftForWorkerAction;
use App\Actions\Time\ListOpenTimeRosterAction;
use App\Actions\Time\LogBlockedClockPointQrAttemptAction;
use App\Actions\Time\ResolveClockPointPortalTokenAction;
use App\Actions\Time\SetWorkerClockPinAction;
use App\Actions\Time\StartWorkBreakAction;
use App\Actions\Time\TransferOpenWorkShiftToClockPointAction;
use App\Enums\ClockDeviceRefusalReason;
use App\Http\Requests\Time\AcknowledgeTimeRosterViewRequest;
use App\Http\Requests\Time\WorkerClockPinRequest;
use App\Livewire\Concerns\PortalTeamleaderManageWorkers;
use App\Livewire\Concerns\PortalTeamleaderRelease;
use App\Livewire\Concerns\SwitchesPortalUiTheme;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Portal\TimePortalData;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerIcon;
use App\Support\Portal\WorkerIconGuard;
use App\Support\Portal\WorkerVerification;
use App\Support\Qr\InvalidQrResponse;
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
    use PortalTeamleaderManageWorkers;
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
    public bool $showRegisterForm = false;
    public string $selected_icon_slug = '';
    public string $flashMessage = '';
    public string $pin_code = '';
    public string $pin_code_confirm = '';
    public ?string $clockGpsLatitude = null;
    public ?string $clockGpsLongitude = null;
    public bool $rosterAckOpen = false;
    public bool $rosterListOpen = false;
    public bool $rosterAcknowledged = false;

    /** Baseline alleen bij openen/login synchen — niet bij elke wire:poll. */
    public bool $taskBaselineSyncedThisVisit = false;

    public function mount(string $token): void
    {
        $resolution = app(ResolveClockPointPortalTokenAction::class)->handle($token);

        if ($resolution->status === ClockPointPortalTokenResolution::STATUS_NOT_FOUND) {
            InvalidQrResponse::abort();
        }

        $clockPoint = $resolution->clockPoint;
        if ($clockPoint === null) {
            InvalidQrResponse::abort();
        }

        if ($resolution->status === ClockPointPortalTokenResolution::STATUS_BLOCKED) {
            Tenancy::actAs($clockPoint->tenant_id);
            app(LogBlockedClockPointQrAttemptAction::class)->handle(
                $clockPoint,
                $token,
                $resolution->historyToken,
            );
            InvalidQrResponse::abort();
        }

        $this->token = $token;
        $this->clockPointId = $clockPoint->id;
        $this->tenantId = $clockPoint->tenant_id;
        $this->clockPointName = $clockPoint->name;

        Tenancy::actAs($this->tenantId);

        $this->inactiveReasonKey = TimePortalData::clockPointInactiveReasonKey($clockPoint);

        $this->syncLocaleFromRequest();
    }

    public function booted(): void
    {
        Tenancy::actAs($this->tenantId);
        app()->setLocale($this->locale);
        $this->enforceClockDeviceForVerifiedSession();
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

        $identity = WorkerDeviceSession::resolveIdentityForTenant(
            $this->tenantId,
            $this->first_name,
            $this->last_name,
            $this->activeClockPoint()?->location_id !== null ? (int) $this->activeClockPoint()->location_id : null,
        );

        if ($identity['status'] === 'ambiguous') {
            $this->addError('identify', __('portal.worker.errors.identify_ambiguous'));
            return;
        }

        if ($identity['status'] === 'not_found') {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));
            return;
        }

        if ($identity['status'] === 'claimable') {
            $this->showRegisterForm = true;
            $this->selected_icon_slug = '';
            $this->resetErrorBag(['identify', 'sign_in_icon_slug', 'selected_icon_slug']);

            return;
        }

        $worker = $identity['worker'] ?? null;
        if ($worker === null) {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));
            return;
        }

        WorkerDeviceSession::bindRememberedWorkerForTenant($worker);
        $this->showRegisterForm = false;
        $this->sign_in_icon_slug = '';
        $this->pin_code = '';
        $this->pin_code_confirm = '';
        $this->resetErrorBag(['identify', 'sign_in_icon_slug', 'selected_icon_slug', 'pin_code', 'pin_code_confirm']);
    }

    public function showRegister(): void
    {
        if ($this->activeClockPoint() === null || TimePortalData::openRegistrationTeam($this->tenantId) === null) {
            return;
        }

        $this->showRegisterForm = true;
        $this->selected_icon_slug = '';
        $this->resetErrorBag(['selected_icon_slug', 'identify']);
    }

    public function cancelRegistration(): void
    {
        $this->showRegisterForm = false;
        $this->selected_icon_slug = '';
        $this->resetErrorBag(['selected_icon_slug', 'identify']);
    }

    public function completeOnboarding(): void
    {
        if ($this->activeClockPoint() === null) {
            return;
        }

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'selected_icon_slug' => ['required', 'string', Rule::in(WorkerIcon::SLUGS)],
        ], [
            'first_name.required' => __('portal.worker.errors.name_required'),
            'last_name.required' => __('portal.worker.errors.name_required'),
            'selected_icon_slug.required' => __('portal.worker.errors.icon_required'),
            'selected_icon_slug.in' => __('portal.worker.errors.icon_required'),
        ]);

        $team = $this->resolveOnboardingTeam($validated['first_name'], $validated['last_name']);
        if ($team === null) {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));

            return;
        }

        $result = WorkerDeviceSession::registerWorkerForTeam(
            $team,
            $validated['first_name'],
            $validated['last_name'],
            $validated['selected_icon_slug'],
        );

        $worker = $result['worker'];
        WorkerDeviceSession::bindRememberedWorkerForTenant($worker);

        if ($this->tenantRequiresPin()) {
            $this->reset(['selected_icon_slug', 'showRegisterForm', 'sign_in_icon_slug']);
            $this->flashMessage = '';
            $this->taskBaselineSyncedThisVisit = false;

            return;
        }

        if (! $this->markPortalVerified($worker)) {
            return;
        }

        $this->reset(['first_name', 'last_name', 'selected_icon_slug', 'showRegisterForm', 'sign_in_icon_slug']);
        $this->flashMessage = __('portal.team.onboarding_done');
        $this->taskBaselineSyncedThisVisit = false;
        app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);
        $this->taskBaselineSyncedThisVisit = true;
    }

    public function signInAsDifferentWorker(): void
    {
        $verified = $this->verifiedWorker();
        $team = $verified?->team;

        WorkerDeviceSession::revokeDeviceSessionFromRequest($team);
        if ($team !== null) {
            WorkerIconGuard::clearSessionForTeam((int) $team->id);
            WorkerVerification::clearForTeam((int) $team->id);
            app(ClearWorkerTaskBaselineAction::class)->handle((int) $team->id);
        }

        $this->taskBaselineSyncedThisVisit = false;
        $this->reset(['first_name', 'last_name', 'sign_in_icon_slug', 'selected_icon_slug', 'showRegisterForm', 'pin_code', 'pin_code_confirm', 'rosterAckOpen', 'rosterListOpen', 'rosterAcknowledged']);
        $this->resetErrorBag(['identify', 'sign_in_icon_slug', 'selected_icon_slug', 'pin_code', 'pin_code_confirm', 'rosterAcknowledged']);
    }

    public function openRoster(): void
    {
        if ($this->authorizedWorker() === null || $this->activeClockPoint() === null) {
            return;
        }

        if (! TimeModuleAccess::tenantHasModule(Tenant::query()->find($this->tenantId))) {
            return;
        }

        $this->rosterAckOpen = true;
        $this->rosterListOpen = false;
        $this->rosterAcknowledged = false;
        $this->resetErrorBag(['rosterAcknowledged']);
    }

    public function closeRoster(): void
    {
        $this->rosterAckOpen = false;
        $this->rosterListOpen = false;
        $this->rosterAcknowledged = false;
        $this->resetErrorBag(['rosterAcknowledged']);
    }

    public function acknowledgeRoster(AcknowledgeTimeRosterViewAction $acknowledge): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null || ! $this->rosterAckOpen) {
            return;
        }

        $this->validate(
            ['rosterAcknowledged' => AcknowledgeTimeRosterViewRequest::rulesFor()['acknowledged']],
            ['rosterAcknowledged.accepted' => AcknowledgeTimeRosterViewRequest::messagesFor()['acknowledged.accepted']],
        );

        try {
            $acknowledge->handle($worker, $this->tenantId);
        } catch (InvalidArgumentException) {
            $this->addError('rosterAcknowledged', __('time.roster.ack_required'));

            return;
        }

        $this->rosterAckOpen = false;
        $this->rosterListOpen = true;
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

        if (! $this->bindClockDeviceAfterVerify($worker)) {
            $this->sign_in_icon_slug = '';

            return;
        }

        $this->sign_in_icon_slug = '';
        $this->flashMessage = __('portal.worker.signed_in');
        $this->taskBaselineSyncedThisVisit = false;
        app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);
        $this->taskBaselineSyncedThisVisit = true;
    }

    public function completePinSetup(SetWorkerClockPinAction $setPin): void
    {
        if ($this->activeClockPoint() === null || ! $this->tenantRequiresPin()) {
            return;
        }

        $deviceWorker = $this->rememberedWorkerForTenant();
        if ($deviceWorker === null) {
            return;
        }

        $this->validate(WorkerClockPinRequest::setupRulesFor(), WorkerClockPinRequest::messagesFor());

        try {
            $setPin->handle($deviceWorker, $this->pin_code, $this->tenantId);
        } catch (InvalidArgumentException) {
            $this->addError('pin_code', __('portal.worker.errors.pin_invalid'));

            return;
        }

        if (! $this->markPortalVerified($deviceWorker->fresh())) {
            $this->pin_code = '';
            $this->pin_code_confirm = '';

            return;
        }

        $this->pin_code = '';
        $this->pin_code_confirm = '';
        $this->flashMessage = __('portal.worker.pin_set');
        $this->taskBaselineSyncedThisVisit = false;
        app(SyncWorkerOpenTaskBaselineAction::class)->handle($deviceWorker);
        $this->taskBaselineSyncedThisVisit = true;
    }

    public function signInWithPin(ConfirmWorkerClockPinAction $confirmPin): void
    {
        if ($this->activeClockPoint() === null || ! $this->tenantRequiresPin()) {
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
            $this->pin_code = '';
            $this->addError('pin_code', __('portal.worker.errors.blocked'));

            return;
        }

        $this->validate(WorkerClockPinRequest::verifyRulesFor(), WorkerClockPinRequest::messagesFor());

        $worker = $confirmPin->handle($deviceWorker, $this->pin_code, $this->tenantId);
        if ($worker === null) {
            WorkerIconGuard::recordFailedAttempt($team);
            $this->pin_code = '';
            if (WorkerIconGuard::isBlocked($team)) {
                $this->addError('pin_code', __('portal.worker.errors.blocked'));
            } else {
                $this->addError('pin_code', __('portal.worker.errors.pin_wrong'));
            }

            return;
        }

        if (! $this->markPortalVerified($worker)) {
            $this->pin_code = '';

            return;
        }

        $this->pin_code = '';
        $this->flashMessage = __('portal.worker.signed_in');
        $this->taskBaselineSyncedThisVisit = false;
        app(SyncWorkerOpenTaskBaselineAction::class)->handle($worker);
        $this->taskBaselineSyncedThisVisit = true;
    }

    public function clockIn(ClockInAction $clockIn, FindOpenWorkShiftForWorkerAction $findShift, TransferOpenWorkShiftToClockPointAction $transfer): void
    {
        $worker = $this->authorizedWorker();
        $clockPoint = $this->activeClockPoint();
        if ($worker === null || $clockPoint === null) {
            return;
        }

        $openShift = $findShift->handle($worker);
        if ($openShift !== null && $openShift->currentClockPointId() !== (int) $clockPoint->id) {
            try {
                [$device, $token] = $this->clockDeviceContext($worker);
                $transfer->handle($worker, $clockPoint, $device, null, true, $token);
                $this->flashMessage = __('time.portal.transferred');
            } catch (InvalidArgumentException $e) {
                if ($this->flashClockDeviceError($e)) {
                    return;
                }
                if ($e->getMessage() === 'shift_already_open') {
                    $this->flashMessage = __('time.portal.errors.already_clocked_in');
                }
            }

            return;
        }

        try {
            [$device, $token] = $this->clockDeviceContext($worker);
            [$lat, $lng] = $this->consumeClockGps();
            $clockIn->handle(
                $worker,
                $clockPoint,
                $device,
                null,
                \App\Enums\ClockSource::ClockPointQr,
                true,
                $token,
                $lat,
                $lng,
            );
            $this->flashMessage = __('time.portal.clocked_in');
        } catch (InvalidArgumentException $e) {
            if ($this->flashClockDeviceError($e)) {
                return;
            }
            if ($e->getMessage() === 'shift_already_open') {
                $this->flashMessage = __('time.portal.errors.already_clocked_in');
            }
        }
    }

    public function transferToThisClockPoint(TransferOpenWorkShiftToClockPointAction $transfer, FindOpenWorkShiftForWorkerAction $findShift): void
    {
        $worker = $this->authorizedWorker();
        $clockPoint = $this->activeClockPoint();
        if ($worker === null || $clockPoint === null) {
            return;
        }

        $openShift = $findShift->handle($worker);
        if ($openShift === null) {
            $this->flashMessage = __('time.portal.errors.not_clocked_in');

            return;
        }

        if ($openShift->currentClockPointId() === (int) $clockPoint->id) {
            $this->flashMessage = __('time.portal.errors.already_clocked_in');

            return;
        }

        try {
            [$device, $token] = $this->clockDeviceContext($worker);
            $transfer->handle($worker, $clockPoint, $device, null, true, $token);
            $this->flashMessage = __('time.portal.transferred');
        } catch (InvalidArgumentException $e) {
            if ($this->flashClockDeviceError($e)) {
                return;
            }
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
            [$device, $token] = $this->clockDeviceContext($worker);
            $clockOut->handle($worker, $clockPoint, null, \App\Enums\ClockSource::ClockPointQr, true, $device, $token);
            $this->flashMessage = __('time.portal.clocked_out');
        } catch (InvalidArgumentException $e) {
            if ($this->flashClockDeviceError($e)) {
                return;
            }
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
            [$device, $token] = $this->clockDeviceContext($worker);
            $startBreak->handle($worker, $shift, true, $device, $token);
            $this->flashMessage = __('time.portal.break_started');
        } catch (InvalidArgumentException $e) {
            if ($this->flashClockDeviceError($e)) {
                return;
            }
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
            [$device, $token] = $this->clockDeviceContext($worker);
            $endBreak->handle($worker, $shift, true, $device, $token);
            $this->flashMessage = __('time.portal.break_ended');
        } catch (InvalidArgumentException $e) {
            if ($this->flashClockDeviceError($e)) {
                return;
            }
            if ($e->getMessage() === 'break_not_open') {
                $this->flashMessage = __('time.portal.errors.break_not_open');
            }
        }
    }

    public function render(FindOpenWorkShiftForWorkerAction $findShift, SyncWorkerOpenTaskBaselineAction $syncBaseline, ListOpenTimeRosterAction $listRoster)
    {
        app()->setLocale($this->locale);

        $verifiedWorker = $this->verifiedWorker();
        $canAct = $verifiedWorker !== null;
        $team = $verifiedWorker?->team;

        if ($canAct && $verifiedWorker !== null && ! $this->taskBaselineSyncedThisVisit) {
            $syncBaseline->handle($verifiedWorker);
            $this->taskBaselineSyncedThisVisit = true;
        }

        $hasAnyWorkers = Worker::query()
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->exists();

        $hasSignInWorkers = Worker::query()
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->whereNotNull('field_icon_slug')
            ->where('field_icon_slug', '!=', '')
            ->exists();

        $openRegistrationTeam = TimePortalData::openRegistrationTeam($this->tenantId);
        $allowOpenRegistration = $openRegistrationTeam !== null;
        $registerOnly = $this->activeClockPoint() !== null
            && ! $canAct
            && ! $hasAnyWorkers
            && $allowOpenRegistration;

        $deviceWorker = null;
        if (! $canAct && ($hasSignInWorkers || $this->showRegisterForm || $this->tenantRequiresPin())) {
            $deviceWorker = $this->rememberedWorkerForTenant();
        }

        $iconBlocked = false;
        if (! $canAct && $deviceWorker?->team !== null) {
            $iconBlocked = WorkerIconGuard::isBlocked($deviceWorker->team);
        }

        $requirePin = $this->tenantRequiresPin();
        $showRegisterForm = $this->showRegisterForm && ! $registerOnly;
        $showIdentify = $this->activeClockPoint() !== null && ! $canAct && $hasAnyWorkers
            && $deviceWorker === null && ! $showRegisterForm && ! $registerOnly && ! $iconBlocked;
        $showPinSetup = $requirePin && $this->activeClockPoint() !== null && ! $canAct
            && $deviceWorker !== null && ! $deviceWorker->hasClockPin()
            && ! $showRegisterForm && ! $registerOnly && ! $iconBlocked;
        $showPinVerify = $requirePin && $this->activeClockPoint() !== null && ! $canAct
            && $deviceWorker !== null && $deviceWorker->hasClockPin()
            && ! $showRegisterForm && ! $registerOnly && ! $iconBlocked;
        $showVerify = ! $requirePin && $this->activeClockPoint() !== null && ! $canAct && $hasSignInWorkers
            && $deviceWorker !== null && ! $showRegisterForm && ! $registerOnly && ! $iconBlocked;
        $showNoWorkers = $this->activeClockPoint() !== null && ! $canAct && ! $hasAnyWorkers
            && ! $registerOnly && ! $showRegisterForm && ! $iconBlocked;

        $openShift = null;
        if ($canAct && $verifiedWorker !== null && TimeModuleAccess::tenantHasModule(Tenant::query()->find($this->tenantId))) {
            $openShift = $findShift->handle($verifiedWorker);
        }
        $hasTimeModule = TimeModuleAccess::tenantHasModule(Tenant::query()->find($this->tenantId));
        $tasks = $canAct && $verifiedWorker !== null ? TimePortalData::openTasksForWorker($verifiedWorker) : collect();
        $teamWorkers = ($team !== null && $verifiedWorker !== null && $verifiedWorker->is_teamleader)
            ? Worker::query()
                ->where('internal_team_id', $team->id)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
            : collect();

        if (! $canAct || ! $hasTimeModule) {
            $this->rosterAckOpen = false;
            $this->rosterListOpen = false;
        }

        $roster = null;
        if ($canAct && $this->rosterListOpen && $verifiedWorker !== null && $hasTimeModule) {
            $roster = $listRoster->handle($this->tenantId);
        }

        return view('livewire.public.time-portal', [
            'canAct' => $canAct,
            'verifiedWorker' => $verifiedWorker,
            'hasSignInWorkers' => $hasSignInWorkers,
            'allowOpenRegistration' => $allowOpenRegistration,
            'registerOnly' => $registerOnly,
            'showRegisterForm' => $showRegisterForm,
            'showIdentify' => $showIdentify,
            'showVerify' => $showVerify,
            'showPinSetup' => $showPinSetup,
            'showPinVerify' => $showPinVerify,
            'showNoWorkers' => $showNoWorkers,
            'iconBlocked' => $iconBlocked,
            'deviceWorker' => $deviceWorker,
            'remainingAttempts' => $deviceWorker?->team !== null
                ? WorkerIconGuard::remainingAttempts($deviceWorker->team)
                : WorkerIconGuard::MAX_FAILED_ATTEMPTS,
            'openShift' => $openShift,
            'tasks' => $tasks,
            'hasTimeModule' => $hasTimeModule,
            'gpsOnClock' => $this->tenantRequestsClockGps(),
            'teamWorkers' => $teamWorkers,
            'manageWorkersMessage' => $this->manageWorkersMessage,
            'roster' => $roster,
            'isTimePortal' => true,
            'isTeamPortal' => false,
        ]);
    }

    private function tenantRequiresPin(): bool
    {
        return TimePortalData::tenantRequiresPin($this->tenantId);
    }

    private function tenantRequestsClockGps(): bool
    {
        return TimePortalData::tenantRequestsClockGps($this->tenantId);
    }

    private function markPortalVerified(Worker $worker): bool
    {
        if (! $this->bindClockDeviceAfterVerify($worker)) {
            return false;
        }

        $team = $worker->team;
        if ($team !== null) {
            WorkerVerification::markVerified($team, $worker);
        }

        return true;
    }

    /**
     * Koppel deze browser als gsm van de uitvoerder, of weiger als al een ander toestel hangt.
     */
    private function bindClockDeviceAfterVerify(Worker $worker): bool
    {
        $worker->refresh();

        try {
            $bound = app(AssertWorkerClockDeviceAction::class)->handle(
                $worker,
                $this->deviceForWorker($worker),
                $this->tenantId,
                WorkerDeviceSession::deviceTokenFromRequest(),
                true,
            );
        } catch (InvalidArgumentException $e) {
            if ($this->flashClockDeviceError($e)) {
                $team = $worker->team;
                if ($team !== null) {
                    WorkerVerification::clearForTeam((int) $team->id);
                }

                return false;
            }

            throw $e;
        }

        WorkerDeviceSession::persistDeviceToken($bound->device_token, $worker->team);
        WorkerDeviceSession::bindRememberedWorkerForTenant($worker);

        return true;
    }

    /**
     * @return array{0: ?\App\Models\WorkerDevice, 1: string}
     */
    private function clockDeviceContext(Worker $worker): array
    {
        return [$this->deviceForWorker($worker), WorkerDeviceSession::deviceTokenFromRequest()];
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function consumeClockGps(): array
    {
        $lat = $this->clockGpsLatitude !== null && $this->clockGpsLatitude !== ''
            ? (float) $this->clockGpsLatitude
            : null;
        $lng = $this->clockGpsLongitude !== null && $this->clockGpsLongitude !== ''
            ? (float) $this->clockGpsLongitude
            : null;
        $this->clockGpsLatitude = null;
        $this->clockGpsLongitude = null;

        return [$lat, $lng];
    }

    private function flashClockDeviceError(InvalidArgumentException $e): bool
    {
        if (ClockDeviceRefusalReason::tryFrom($e->getMessage()) === null) {
            return false;
        }

        $this->flashMessage = __('time.portal.errors.device_mismatch');

        return true;
    }

    private function resolveOnboardingTeam(string $firstName, string $lastName): ?InternalTeam
    {
        $identity = WorkerDeviceSession::resolveIdentityForTenant(
            $this->tenantId,
            $firstName,
            $lastName,
            $this->activeClockPoint()?->location_id !== null ? (int) $this->activeClockPoint()->location_id : null,
        );
        if ($identity['status'] === 'claimable') {
            $worker = $identity['worker'] ?? null;

            return $worker?->team;
        }

        $openTeam = TimePortalData::openRegistrationTeam($this->tenantId);
        if ($openTeam !== null && ($this->showRegisterForm || $identity['status'] === 'not_found')) {
            return $openTeam;
        }

        return null;
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
        $verified = $this->sessionVerifiedWorker();
        if ($verified === null) {
            return null;
        }

        if (! $this->workerSessionMatchesBoundDevice($verified)) {
            $team = $verified->team;
            if ($team !== null) {
                WorkerVerification::clearForTeam((int) $team->id);
            }

            return null;
        }

        return $verified;
    }

    private function sessionVerifiedWorker(): ?Worker
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

    /**
     * Open sessies zonder gekoppeld toestel (of met een ander toestel) mogen niet actief blijven.
     */
    private function enforceClockDeviceForVerifiedSession(): void
    {
        if ($this->tenantId < 1) {
            return;
        }

        $worker = $this->sessionVerifiedWorker();
        if ($worker === null) {
            return;
        }

        $device = $this->deviceForWorker($worker);
        $boundId = $worker->clock_device_id !== null ? (int) $worker->clock_device_id : null;
        if ($boundId !== null && $device !== null && (int) $device->id === $boundId) {
            return;
        }

        $this->bindClockDeviceAfterVerify($worker);
    }

    private function workerSessionMatchesBoundDevice(Worker $worker): bool
    {
        $boundId = $worker->clock_device_id !== null ? (int) $worker->clock_device_id : null;
        $device = $this->deviceForWorker($worker);

        return $boundId !== null && $device !== null && (int) $device->id === $boundId;
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

    protected function portalManageWorkersTeam(): ?\App\Models\InternalTeam
    {
        return $this->verifiedWorker()?->team;
    }

    protected function portalManageWorkersFlash(string $message): void
    {
        $this->flashMessage = $message;
    }
}

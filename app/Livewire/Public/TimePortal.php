<?php

namespace App\Livewire\Public;

use App\Actions\Notifications\ListWorkerNotificationsAction;
use App\Actions\Notifications\MarkWorkerNotificationsReadAction;
use App\Actions\Portal\SyncWorkerOpenTaskBaselineAction;
use App\Actions\Tasks\CompleteTaskAction;
use App\Actions\Tasks\RoundTaskCompletionAction;
use App\Actions\Tasks\SkipRoundStopAction;
use App\Actions\Tasks\StartTaskAction;
use App\Actions\Time\AcknowledgeTimeRosterViewAction;
use App\Actions\Time\CancelAbsenceRequestAction;
use App\Actions\Time\AssertClockPointTaskVisitAction;
use App\Actions\Time\AssertClockPointUnitVisitAction;
use App\Actions\Time\AssertWorkerClockDeviceAction;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\ClockOutAction;
use App\Actions\Time\ConfirmWorkerClockPinAction;
use App\Actions\Time\EndWorkBreakAction;
use App\Actions\Time\EndWorkVisitAction;
use App\Actions\Time\FindOpenWorkShiftForWorkerAction;
use App\Actions\Time\ListOpenTimeRosterAction;
use App\Actions\Time\ListPublishedWorkerRosterAction;
use App\Actions\Time\ListWorkDestinationsForWorkerAction;
use App\Actions\Time\ListWorkerAbsenceRequestsAction;
use App\Actions\Time\ListWorkerHoursAction;
use App\Actions\Time\RequestAbsenceAction;
use App\Actions\Time\LogBlockedClockPointQrAttemptAction;
use App\Actions\Time\ResolveClockPointPortalTokenAction;
use App\Actions\Time\ResolveRosterMonthAction;
use App\Actions\Time\ResolveWorkerPortalRosterAlertsAction;
use App\Actions\Time\SetWorkerClockPinAction;
use App\Actions\Time\StartWorkBreakAction;
use App\Actions\Time\StartWorkVisitAction;
use App\Actions\Time\SuggestNearbyClockUnitsAction;
use App\Actions\Time\TransferOpenWorkShiftToClockPointAction;
use App\Actions\Units\RecordUnitCheckAndApplyTasksAction;
use App\Actions\Units\ResolveOpenUnitTaskForCheckAction;
use App\Data\Time\RequestAbsenceData;
use App\Data\Units\RecordUnitCheckData;
use App\Enums\ClockDeviceRefusalReason;
use App\Enums\ShiftTypeKind;
use App\Enums\ClockSource;
use App\Enums\TaskStatus;
use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use App\Enums\WorkerNotificationType;
use App\Http\Requests\Esg\RecordEsgMeasurementRequest;
use App\Http\Requests\Public\CompletePortalTaskRequest;
use App\Http\Requests\Time\AcknowledgeTimeRosterViewRequest;
use App\Http\Requests\Time\ListWorkerHoursRequest;
use App\Http\Requests\Time\RequestAbsenceRequest;
use App\Http\Requests\Time\WorkerClockPinRequest;
use App\Http\Requests\Units\RecordUnitCheckRequest;
use App\Livewire\Concerns\PortalTeamleaderManageWorkers;
use App\Livewire\Concerns\PortalTeamleaderRelease;
use App\Livewire\Concerns\SwitchesPortalUiTheme;
use App\Models\AbsenceRequest;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Worker;
use App\Models\WorkerDevice;
use App\Support\Portal\ClockPointScanGrant;
use App\Support\Portal\TimePortalData;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerIcon;
use App\Support\Portal\WorkerIconGuard;
use App\Support\Portal\WorkerVerification;
use App\Support\Qr\InvalidQrResponse;
use App\Support\ResolveAppLocale;
use App\Support\Tenancy;
use App\Support\Time\ClockPointPortalTokenResolution;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Time-portaal (Clock Point QR): in-/uitklokken, pauze, eigen uren.
 * Taken en inspectiestops afhandelen alleen met GPS-werkbezoek op die locatie.
 */
#[Layout('components.layouts.public')]
#[Title('WinProx')]
class TimePortal extends Component
{
    use PortalTeamleaderManageWorkers;
    use PortalTeamleaderRelease;
    use SwitchesPortalUiTheme;
    use WithFileUploads;

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

    /** @var list<array{unit_id: ?int, location_id: int, unit_name: string, location_name: string, distance_meters: int}> */
    public array $nearbyClockUnits = [];

    public bool $nearbyClockUnitsLoaded = false;

    public ?int $completingTaskId = null;

    public string $completingNote = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $completingPhotos = [];

    public ?string $completingEsgValueNumeric = null;

    public ?bool $completingEsgValueBoolean = null;

    public string $completingEsgValueString = '';

    public string $completingEsgValueJson = '';

    /** @var list<string> */
    public array $completingEsgValueMultiChoice = [];

    public ?string $completingRecordedAt = null;

    public ?int $checkingUnitId = null;

    public string $checkResult = '';

    public ?float $checkLatitude = null;

    public ?float $checkLongitude = null;

    public ?string $checkCheckedAt = null;

    /** @var list<string> */
    public array $checkChecklistItems = [];

    public string $checkDescription = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $checkPhotos = [];

    public ?int $skipRoundTaskId = null;

    public string $skipReason = '';

    #[Locked]
    public bool $rosterAckOpen = false;

    #[Locked]
    public bool $rosterListOpen = false;

    #[Locked]
    public bool $hoursListOpen = false;

    #[Locked]
    public bool $scheduleListOpen = false;

    #[Locked]
    public bool $absenceListOpen = false;

    public string $absenceKind = 'leave';

    public string $absenceDateFrom = '';

    public string $absenceDateTo = '';

    public string $absenceDescription = '';

    public string $scheduleMonth = '';

    public string $hoursMonth = '';

    public bool $rosterAcknowledged = false;

    /** Baseline alleen bij openen/login synchen — niet bij elke wire:poll. */
    public bool $taskBaselineSyncedThisVisit = false;

    public bool $homescreenHelpOpen = false;

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

        if ($this->inactiveReasonKey === null) {
            ClockPointScanGrant::grant($this->clockPointId);
        }
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

    public function signOut(): void
    {
        $verified = $this->verifiedWorker();
        $team = $verified?->team;
        if ($team !== null) {
            WorkerVerification::clearForTeam((int) $team->id);
        }

        $this->forgetPortalSignInState();
    }

    public function openHomescreenHelp(): void
    {
        if (! $this->clockPointOffersHomescreenShortcut()) {
            return;
        }

        $this->homescreenHelpOpen = true;
    }

    public function closeHomescreenHelp(): void
    {
        $this->homescreenHelpOpen = false;
    }

    private function forgetPortalSignInState(): void
    {
        $this->taskBaselineSyncedThisVisit = false;
        $this->reset(['first_name', 'last_name', 'sign_in_icon_slug', 'selected_icon_slug', 'showRegisterForm', 'pin_code', 'pin_code_confirm', 'rosterAckOpen', 'rosterListOpen', 'hoursListOpen', 'hoursMonth', 'rosterAcknowledged', 'scheduleListOpen', 'scheduleMonth', 'absenceListOpen', 'absenceKind', 'absenceDateFrom', 'absenceDateTo', 'absenceDescription', 'completingTaskId', 'checkingUnitId', 'skipRoundTaskId', 'flashMessage', 'homescreenHelpOpen']);
        $this->resetErrorBag(['identify', 'sign_in_icon_slug', 'selected_icon_slug', 'pin_code', 'pin_code_confirm', 'rosterAcknowledged']);
    }

    public function openRoster(): void
    {
        if ($this->authorizedWorker() === null || $this->activeClockPoint() === null) {
            return;
        }

        try {
            TimeModuleAccess::assertEnabledForTenantId($this->tenantId);
        } catch (InvalidArgumentException) {
            return;
        }

        if (! TimePortalData::tenantAllowsEvacuationList($this->tenantId)) {
            return;
        }

        $this->hoursListOpen = false;
        $this->scheduleListOpen = false;
        $this->absenceListOpen = false;
        $this->rosterAckOpen = true;
        $this->rosterListOpen = false;
        $this->rosterAcknowledged = false;
        $this->resetErrorBag(['rosterAcknowledged']);
    }

    public function openHours(): void
    {
        if ($this->authorizedWorker() === null || $this->activeClockPoint() === null) {
            return;
        }

        try {
            TimeModuleAccess::assertEnabledForTenantId($this->tenantId);
        } catch (InvalidArgumentException) {
            return;
        }

        $this->closeRoster();
        $this->scheduleListOpen = false;
        $this->absenceListOpen = false;
        $this->hoursMonth = now()->format('Y-m');
        $this->hoursListOpen = true;
    }

    public function closeHours(): void
    {
        $this->hoursListOpen = false;
    }

    public function openSchedule(
        ListWorkerNotificationsAction $listNotifications,
        MarkWorkerNotificationsReadAction $markRead,
        ResolveRosterMonthAction $resolveMonth,
    ): void {
        $worker = $this->authorizedWorker();
        if ($worker === null || $this->activeClockPoint() === null) {
            return;
        }

        try {
            TimeModuleAccess::assertEnabledForTenantId($this->tenantId);
        } catch (InvalidArgumentException) {
            return;
        }

        $this->closeRoster();
        $this->hoursListOpen = false;
        $this->absenceListOpen = false;

        $unread = $listNotifications->handle(
            $worker,
            $this->tenantId,
            WorkerNotificationType::RosterPublished,
            true,
        );
        $newest = $unread[0] ?? null;
        $cursor = $newest?->target->cursor ?? now()->toDateString();
        [$monthStart] = $resolveMonth->handle($cursor);
        $this->scheduleMonth = $monthStart->toDateString();
        $markRead->handle($worker, $this->tenantId, WorkerNotificationType::RosterPublished);
        $this->scheduleListOpen = true;
    }

    public function closeSchedule(): void
    {
        $this->scheduleListOpen = false;
    }

    public function openAbsence(): void
    {
        if ($this->authorizedWorker() === null || $this->activeClockPoint() === null) {
            return;
        }

        try {
            TimeModuleAccess::assertEnabledForTenantId($this->tenantId);
        } catch (InvalidArgumentException) {
            return;
        }

        $this->closeRoster();
        $this->hoursListOpen = false;
        $this->scheduleListOpen = false;
        $today = now()->toDateString();
        $this->absenceKind = ShiftTypeKind::Leave->value;
        $this->absenceDateFrom = $today;
        $this->absenceDateTo = $today;
        $this->absenceDescription = '';
        $this->resetErrorBag(['absenceKind', 'absenceDateFrom', 'absenceDateTo', 'absenceDescription']);
        $this->absenceListOpen = true;
    }

    public function closeAbsence(): void
    {
        $this->absenceListOpen = false;
    }

    public function submitAbsence(RequestAbsenceAction $requestAbsence): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null || $this->activeClockPoint() === null) {
            return;
        }

        $rules = RequestAbsenceRequest::rulesFor();
        $this->validate([
            'absenceKind' => $rules['kind'],
            'absenceDateFrom' => $rules['date_from'],
            'absenceDateTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:absenceDateFrom'],
            'absenceDescription' => $rules['description'],
        ]);

        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $kind = ShiftTypeKind::tryFrom($this->absenceKind);
        if ($kind === null) {
            return;
        }

        try {
            $requestAbsence->handle(
                $tenant,
                $worker,
                new RequestAbsenceData(
                    $kind,
                    $this->absenceDateFrom,
                    $this->absenceDateTo,
                    $this->absenceDescription !== '' ? $this->absenceDescription : null,
                ),
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('absenceDateFrom', __('time.absence.errors.'.$e->getMessage()));

            return;
        }

        $this->absenceDescription = '';
        $this->flashMessage = __('time.portal.absence.submitted');
    }

    public function cancelAbsence(int $id, CancelAbsenceRequestAction $cancel): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null || $this->activeClockPoint() === null) {
            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $request = AbsenceRequest::query()
            ->where('tenant_id', $this->tenantId)
            ->where('worker_id', $worker->id)
            ->find($id);

        if ($request === null) {
            return;
        }

        try {
            $cancel->handle($tenant, $worker, $request);
        } catch (InvalidArgumentException $e) {
            $this->flashMessage = __('time.absence.errors.'.$e->getMessage());

            return;
        }

        $this->flashMessage = __('time.portal.absence.cancelled');
    }

    public function previousScheduleMonth(ResolveRosterMonthAction $resolveMonth): void
    {
        if (! $this->scheduleListOpen) {
            return;
        }

        [$start] = $resolveMonth->handle($this->scheduleMonth !== '' ? $this->scheduleMonth : now()->toDateString());
        $this->scheduleMonth = $start->subMonthNoOverflow()->startOfMonth()->toDateString();
    }

    public function nextScheduleMonth(ResolveRosterMonthAction $resolveMonth): void
    {
        if (! $this->scheduleListOpen) {
            return;
        }

        [$start] = $resolveMonth->handle($this->scheduleMonth !== '' ? $this->scheduleMonth : now()->toDateString());
        $this->scheduleMonth = $start->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    public function previousHoursMonth(): void
    {
        if (! $this->hoursListOpen) {
            return;
        }

        $start = $this->hoursMonthStart();
        if ($start === null) {
            return;
        }

        $this->hoursMonth = $start->subMonth()->format('Y-m');
    }

    public function nextHoursMonth(): void
    {
        if (! $this->hoursListOpen) {
            return;
        }

        $start = $this->hoursMonthStart();
        if ($start === null) {
            return;
        }

        $next = $start->addMonth()->startOfMonth();
        if ($next->gt(now()->startOfMonth())) {
            return;
        }

        $this->hoursMonth = $next->format('Y-m');
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

        if (! $this->requirePunchScanGrant()) {
            return;
        }

        $openShift = $findShift->handle($worker);
        if ($openShift !== null && $openShift->currentClockPointId() !== (int) $clockPoint->id) {
            try {
                [$device, $token] = $this->clockDeviceContext($worker);
                $transfer->handle($worker, $clockPoint, $device, null, true, $token);
                ClockPointScanGrant::consume((int) $clockPoint->id);
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
                ClockSource::ClockPointQr,
                true,
                $token,
                $lat,
                $lng,
            );
            ClockPointScanGrant::consume((int) $clockPoint->id);
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

        if (! $this->requirePunchScanGrant()) {
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
            ClockPointScanGrant::consume((int) $clockPoint->id);
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

        if (! $this->requirePunchScanGrant()) {
            return;
        }

        try {
            [$device, $token] = $this->clockDeviceContext($worker);
            $clockOut->handle($worker, $clockPoint, null, ClockSource::ClockPointQr, true, $device, $token);
            ClockPointScanGrant::consume((int) $clockPoint->id);
        } catch (InvalidArgumentException $e) {
            if ($this->flashClockDeviceError($e)) {
                return;
            }
            if ($e->getMessage() === 'shift_not_open') {
                $this->flashMessage = __('time.portal.errors.not_clocked_in');
            }
        }
    }

    public function refreshNearbyClockUnits(mixed $latitude, mixed $longitude, SuggestNearbyClockUnitsAction $suggest): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null) {
            return;
        }

        $lat = $this->parseVisitGps($latitude);
        $lng = $this->parseVisitGps($longitude);
        if ($lat === null || $lng === null) {
            $this->flashMessage = __('time.portal.errors.visit_gps_required');

            return;
        }

        try {
            $this->nearbyClockUnits = array_map(
                fn ($row) => $row->toArray(),
                $suggest->handle($worker, $lat, $lng),
            );
            $this->nearbyClockUnitsLoaded = true;
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);
        }
    }

    public function startWorkVisit(int $unitId, mixed $latitude, mixed $longitude, int $locationId, StartWorkVisitAction $startVisit): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null) {
            return;
        }

        $lat = $this->parseVisitGps($latitude);
        $lng = $this->parseVisitGps($longitude);
        if ($lat === null || $lng === null) {
            $this->flashMessage = __('time.portal.errors.visit_gps_required');

            return;
        }

        $place = null;
        if ($unitId > 0) {
            $place = Unit::query()
                ->where('tenant_id', $this->tenantId)
                ->whereKey($unitId)
                ->first();
        } elseif ($locationId > 0) {
            $place = Location::query()
                ->where('tenant_id', $this->tenantId)
                ->whereKey($locationId)
                ->first();
        }

        if ($place === null) {
            $this->flashMessage = __('time.portal.errors.visit_unit_out_of_range');

            return;
        }

        try {
            $startVisit->handle($worker, $place, $lat, $lng);
            $this->nearbyClockUnits = [];
            $this->nearbyClockUnitsLoaded = false;
            $this->flashMessage = __('time.portal.visit_started');
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);
        }
    }

    public function endWorkVisit(mixed $latitude, mixed $longitude, EndWorkVisitAction $endVisit): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null) {
            return;
        }

        $lat = $this->parseVisitGps($latitude);
        $lng = $this->parseVisitGps($longitude);
        if ($lat === null || $lng === null) {
            $this->flashMessage = __('time.portal.errors.visit_gps_required');

            return;
        }

        try {
            $endVisit->handle($worker, required: true, latitude: $lat, longitude: $lng);
            $this->flashMessage = __('time.portal.visit_ended');
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);
        }
    }

    public function openClockPointUnitCheck(int $unitId, AssertClockPointUnitVisitAction $assertVisit): void
    {
        $worker = $this->authorizedWorker();
        $unit = $worker !== null ? $this->findClockPointUnit($worker, $unitId) : null;
        if ($unit === null) {
            return;
        }

        try {
            $assertVisit->handle($worker, $unit);
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);

            return;
        }

        $this->checkingUnitId = $unit->id;
        $this->resetClockPointUnitCheckForm();
        $visit = app(FindOpenWorkShiftForWorkerAction::class)->handle($worker)?->openVisit;
        $this->checkLatitude = $visit?->start_latitude;
        $this->checkLongitude = $visit?->start_longitude;
        $this->checkCheckedAt = now()->toIso8601String();
        $this->cancelCompleteTask();
        $this->dispatch('wp-prepare-photo-inputs');
    }

    public function closeClockPointUnitCheck(): void
    {
        $this->checkingUnitId = null;
        $this->resetClockPointUnitCheckForm();
        $this->closeSkipRoundStop();
        $this->dispatch('wp-clear-photo-previews');
    }

    public function removeCheckPhoto(int $index): void
    {
        if (isset($this->checkPhotos[$index])) {
            array_splice($this->checkPhotos, $index, 1);
        }
    }

    public function submitClockPointUnitCheck(
        string $resultValue,
        RecordUnitCheckAndApplyTasksAction $recordCheckAndApply,
        ResolveOpenUnitTaskForCheckAction $resolveOpenTask,
        AssertClockPointUnitVisitAction $assertVisit,
    ): void {
        $worker = $this->authorizedWorker();
        $unit = $worker !== null && $this->checkingUnitId !== null
            ? $this->findClockPointUnit($worker, $this->checkingUnitId)
            : null;
        if ($unit === null) {
            return;
        }

        try {
            $assertVisit->handle($worker, $unit);
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);

            return;
        }

        $this->checkResult = $resultValue;

        if (! $unit->allowsUnitChecks()) {
            $this->addError('checkResult', __('portal.worker.errors.no_permission'));

            return;
        }

        if ($this->checkCheckedAt === null || $this->checkCheckedAt === '') {
            $this->checkCheckedAt = now()->toIso8601String();
        }

        $this->validate(
            RecordUnitCheckRequest::portalRuleSet(),
            RecordUnitCheckRequest::portalValidationMessages(),
        );

        $checkedAtValidator = Validator::make(
            ['checkCheckedAt' => $this->checkCheckedAt],
            ['checkCheckedAt' => ['required', 'date']],
        );
        RecordUnitCheckRequest::assertPortalCheckedAt((string) $this->checkCheckedAt, $checkedAtValidator);
        if ($checkedAtValidator->fails()) {
            foreach ($checkedAtValidator->errors()->getMessages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $unit->loadMissing(['unitCheckList.items']);
        $result = UnitCheckResult::from($this->checkResult);
        $requiredLabels = $unit->unitCheckList?->is_active
            ? $unit->unitCheckList->items->pluck('label')->all()
            : [];
        $selectedLabels = array_values(array_unique(array_filter(
            array_map(static fn ($item) => is_string($item) ? trim($item) : '', $this->checkChecklistItems),
            static fn (string $label) => $label !== '',
        )));

        if ($result === UnitCheckResult::Ok && $requiredLabels !== []) {
            $missing = array_values(array_diff($requiredLabels, $selectedLabels));
            if ($missing !== []) {
                $this->addError('checkChecklistItems', __('portal.unit_check.errors.checklist_incomplete'));

                return;
            }
            $selectedLabels = $requiredLabels;
        } elseif ($requiredLabels !== []) {
            $selectedLabels = array_values(array_intersect($requiredLabels, $selectedLabels));
        } else {
            $selectedLabels = [];
        }

        $waitingRound = $result === UnitCheckResult::Ok
            ? $resolveOpenTask->findRoundWaitingOnEarlierStop($unit, $worker)
            : null;

        $recordCheckAndApply->handle(
            unit: $unit,
            data: new RecordUnitCheckData(
                result: $result,
                checkedAt: CarbonImmutable::parse((string) $this->checkCheckedAt),
                source: UnitCheckSource::Portal,
                latitude: $this->checkLatitude,
                longitude: $this->checkLongitude,
                checklistItems: $selectedLabels === [] ? null : $selectedLabels,
                description: trim($this->checkDescription),
                photos: $this->checkPhotos,
            ),
            tenantId: $this->tenantId,
            worker: $worker,
        );

        $this->closeClockPointUnitCheck();
        if ($waitingRound !== null) {
            $progress = app(RoundTaskCompletionAction::class)->progress($waitingRound);
            $this->flashMessage = __('portal.round.out_of_order', [
                'name' => $progress['next_unit_name'] ?? '—',
            ]);
        } elseif ($result === UnitCheckResult::NotOk) {
            $this->flashMessage = __('portal.unit_check.recorded_not_ok');
        } else {
            $this->flashMessage = __('portal.unit_check.recorded_ok');
        }
    }

    public function openSkipRoundStop(int $taskId): void
    {
        $worker = $this->authorizedWorker();
        $unit = $worker !== null && $this->checkingUnitId !== null
            ? $this->findClockPointUnit($worker, $this->checkingUnitId)
            : null;
        $task = $worker !== null ? $this->findClockPointRoundTask($worker, $taskId) : null;
        if ($unit === null || $task === null) {
            return;
        }

        $this->skipRoundTaskId = $task->id;
        $this->skipReason = '';
        $this->resetErrorBag(['skipReason']);
    }

    public function closeSkipRoundStop(): void
    {
        $this->skipRoundTaskId = null;
        $this->skipReason = '';
        $this->resetErrorBag(['skipReason']);
    }

    public function submitSkipRoundStop(SkipRoundStopAction $skipRoundStop, AssertClockPointUnitVisitAction $assertVisit): void
    {
        $worker = $this->authorizedWorker();
        $unit = $worker !== null && $this->checkingUnitId !== null
            ? $this->findClockPointUnit($worker, $this->checkingUnitId)
            : null;
        $task = $worker !== null && $this->skipRoundTaskId !== null
            ? $this->findClockPointRoundTask($worker, $this->skipRoundTaskId)
            : null;
        if ($unit === null || $task === null) {
            return;
        }

        try {
            $assertVisit->handle($worker, $unit);
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);

            return;
        }

        $skipRoundStop->handle($task, (int) $unit->id, $this->skipReason, $worker);
        $this->closeClockPointUnitCheck();
        $this->flashMessage = __('portal.round.skip_recorded');
    }

    public function startTask(int $taskId, StartTaskAction $startTask, AssertClockPointTaskVisitAction $assertVisit): void
    {
        $worker = $this->authorizedWorker();
        $task = $worker !== null ? $this->findClockPointTask($worker, $taskId) : null;
        if ($task === null) {
            return;
        }

        try {
            $assertVisit->handle($worker, $task);
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);

            return;
        }

        $startTask->handle($task, $worker);
        $this->cancelCompleteTask();
        $this->flashMessage = __('portal.worker.task_started');
    }

    public function beginCompleteTask(int $taskId, AssertClockPointTaskVisitAction $assertVisit): void
    {
        $worker = $this->authorizedWorker();
        $task = $worker !== null ? $this->findClockPointTask($worker, $taskId) : null;
        if ($task === null || ! $task->canComplete()) {
            return;
        }

        try {
            $assertVisit->handle($worker, $task);
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);

            return;
        }

        $this->completingTaskId = $task->id;
        $this->completingNote = '';
        $this->completingPhotos = [];
        $this->resetClockPointEsgFields();
        $this->completingRecordedAt = now()->toIso8601String();
        $this->dispatch('wp-prepare-photo-inputs');
    }

    public function cancelCompleteTask(): void
    {
        $this->completingTaskId = null;
        $this->completingNote = '';
        $this->completingPhotos = [];
        $this->resetClockPointEsgFields();
        $this->dispatch('wp-clear-photo-previews');
    }

    public function removeCompletingPhoto(int $index): void
    {
        if (isset($this->completingPhotos[$index])) {
            array_splice($this->completingPhotos, $index, 1);
        }
    }

    public function submitCompleteTask(
        CompleteTaskAction $completeTask,
        AssertClockPointTaskVisitAction $assertVisit,
    ): void {
        $worker = $this->authorizedWorker();
        if ($worker === null || $this->completingTaskId === null) {
            return;
        }

        $this->validate(
            CompletePortalTaskRequest::ruleSet(),
            CompletePortalTaskRequest::validationMessages(),
        );

        $task = $this->findClockPointTask($worker, $this->completingTaskId);
        if ($task === null) {
            return;
        }

        try {
            $assertVisit->handle($worker, $task);
        } catch (InvalidArgumentException $e) {
            $this->flashVisitError($e);

            return;
        }

        $task->loadMissing(['issue.esgIndicator']);
        $esgIndicator = $task->issue?->esgIndicator;
        $esgType = $esgIndicator?->type;

        $this->validate(
            CompletePortalTaskRequest::ruleSet($esgType),
            CompletePortalTaskRequest::validationMessages($esgType),
        );

        if ($esgType !== null) {
            if ($this->completingRecordedAt === null || $this->completingRecordedAt === '') {
                $this->completingRecordedAt = now()->toIso8601String();
            }
            $recordedAtValidator = Validator::make(
                ['completingRecordedAt' => $this->completingRecordedAt],
                ['completingRecordedAt' => ['required', 'date']],
            );
            RecordEsgMeasurementRequest::assertPortalRecordedAt((string) $this->completingRecordedAt, $recordedAtValidator);
            if ($recordedAtValidator->fails()) {
                foreach ($recordedAtValidator->errors()->getMessages() as $field => $messages) {
                    foreach ($messages as $message) {
                        $this->addError($field, $message);
                    }
                }

                return;
            }
        }

        $esgMeasurement = null;
        $clientTimestamp = null;
        if ($esgIndicator !== null && filled($this->completingRecordedAt)) {
            $esgMeasurement = RecordEsgMeasurementRequest::portalToData(
                $task->id,
                $esgIndicator,
                (string) $this->completingRecordedAt,
                [
                    'completingEsgValueNumeric' => $this->completingEsgValueNumeric,
                    'completingEsgValueBoolean' => $this->completingEsgValueBoolean,
                    'completingEsgValueString' => $this->completingEsgValueString,
                    'completingEsgValueJson' => $this->completingEsgValueJson,
                    'completingEsgValueMultiChoice' => $this->completingEsgValueMultiChoice,
                ],
            );
            $clientTimestamp = Carbon::parse((string) $this->completingRecordedAt);
        }

        try {
            $completeTask->handle(
                $task,
                $worker,
                $this->completingNote,
                $this->completingPhotos,
                $clientTimestamp,
                $esgMeasurement,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $this->cancelCompleteTask();
        $this->flashMessage = __('portal.worker.task_completed');
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

    public function render(FindOpenWorkShiftForWorkerAction $findShift, SyncWorkerOpenTaskBaselineAction $syncBaseline, ListOpenTimeRosterAction $listRoster, ListWorkerHoursAction $listHours, ListPublishedWorkerRosterAction $listSchedule, ListWorkerNotificationsAction $listNotifications, ListWorkDestinationsForWorkerAction $listDestinations, ResolveWorkerPortalRosterAlertsAction $rosterAlerts, ListWorkerAbsenceRequestsAction $listAbsenceRequests)
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
        $lastClosedShift = ($canAct && $verifiedWorker !== null && $hasTimeModule && $openShift === null)
            ? TimePortalData::lastClosedShiftToday($verifiedWorker)
            : null;
        $evacuationList = TimePortalData::tenantAllowsEvacuationList($this->tenantId);
        $gpsVisits = TimePortalData::tenantAllowsGpsWorkVisits($this->tenantId);
        $tasks = $canAct && $verifiedWorker !== null ? TimePortalData::openTasksForWorker($verifiedWorker) : collect();
        $checkingUnit = null;
        $clockPointUnitCheckList = null;
        $clockPointUnitCheckListItems = collect();
        $clockPointRoundTask = null;
        $clockPointRoundProgress = null;
        $clockPointIsNextStop = false;
        if ($canAct && $verifiedWorker !== null && $this->checkingUnitId !== null) {
            $checkingUnit = $this->findClockPointUnit($verifiedWorker, $this->checkingUnitId);
            if ($checkingUnit !== null) {
                $checkingUnit->loadMissing(['unitCheckList.items', 'unitCheckList.translations']);
                if ($checkingUnit->unitCheckList?->is_active) {
                    $clockPointUnitCheckList = $checkingUnit->unitCheckList;
                    $clockPointUnitCheckListItems = $clockPointUnitCheckList->items;
                }
                $clockPointRoundTask = app(ResolveOpenUnitTaskForCheckAction::class)
                    ->handle($checkingUnit, $verifiedWorker, prefer: 'round')
                    ?? app(ResolveOpenUnitTaskForCheckAction::class)
                        ->findRoundWaitingOnEarlierStop($checkingUnit, $verifiedWorker);
                if ($clockPointRoundTask !== null) {
                    $clockPointRoundProgress = app(RoundTaskCompletionAction::class)->progress($clockPointRoundTask);
                    $clockPointIsNextStop = app(RoundTaskCompletionAction::class)
                        ->isNextOpenStop($clockPointRoundTask, (int) $checkingUnit->id);
                }
            } else {
                $this->checkingUnitId = null;
            }
        }
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
            $this->hoursListOpen = false;
            $this->scheduleListOpen = false;
            $this->absenceListOpen = false;
        }

        if (! $evacuationList) {
            $this->rosterAckOpen = false;
            $this->rosterListOpen = false;
        }

        $roster = null;
        if ($canAct && $this->rosterListOpen && $verifiedWorker !== null && $evacuationList) {
            $roster = $listRoster->handle($this->tenantId);
        }

        $hours = null;
        $hoursMonthLabel = '';
        $hoursIsCurrentMonth = true;
        if ($canAct && $this->hoursListOpen && $verifiedWorker !== null && $hasTimeModule) {
            $from = $this->hoursMonthStart() ?? now()->startOfMonth();
            $this->hoursMonth = $from->format('Y-m');
            $hours = $listHours->handle($verifiedWorker, $this->tenantId, $from, $from->copy()->endOfMonth());
            $hoursMonthLabel = $from->copy()->locale($this->locale)->translatedFormat('F Y');
            $hoursIsCurrentMonth = $from->isSameMonth(now());
        }

        $schedule = null;
        $scheduleMonthLabel = '';
        $scheduleUnreadCount = 0;
        if ($canAct && $verifiedWorker !== null && $hasTimeModule) {
            $scheduleUnreadCount = count($listNotifications->handle(
                $verifiedWorker,
                $this->tenantId,
                WorkerNotificationType::RosterPublished,
                true,
            ));
            if ($this->scheduleListOpen) {
                $cursor = $this->scheduleMonth !== '' ? $this->scheduleMonth : now()->toDateString();
                $schedule = $listSchedule->handle($verifiedWorker, $this->tenantId, $cursor, $this->locale);
                $this->scheduleMonth = $schedule->monthStart;
                $scheduleMonthLabel = $schedule->monthLabel;
            }
        }

        $absenceRequests = collect();
        if ($canAct && $this->absenceListOpen && $verifiedWorker !== null && $hasTimeModule) {
            $absenceRequests = $listAbsenceRequests->handle($verifiedWorker, $this->tenantId);
        }

        $todayClockAlert = null;
        if ($canAct && $verifiedWorker !== null && $hasTimeModule && ! $this->hoursListOpen && ! $this->scheduleListOpen && ! $this->absenceListOpen) {
            $todayClockAlert = $rosterAlerts->handle(
                $verifiedWorker,
                $this->tenantId,
                [now()->toDateString()],
            )[now()->toDateString()] ?? null;
        }

        $todayDestinations = $this->todayDestinationsForView(
            $verifiedWorker,
            $openShift,
            $listDestinations,
        );
        $onSiteGuidance = ($gpsVisits && $openShift?->openVisit !== null)
            ? TimePortalData::onSiteGuidance($openShift->openVisit, $tasks, $todayDestinations)
            : null;

        return view('livewire.public.time-portal', [
            'canAct' => $canAct,
            'verifiedWorker' => $verifiedWorker,
            'signedInAt' => $team !== null ? WorkerVerification::verifiedAt($team) : null,
            'tenantName' => (string) (Tenant::query()->whereKey($this->tenantId)->value('name') ?? ''),
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
            'lastClosedShift' => $lastClosedShift,
            'todayClockAlert' => $todayClockAlert,
            'todayDestinations' => $todayDestinations,
            'onSiteGuidance' => $onSiteGuidance,
            'openVisitUnitId' => $openShift?->openVisit?->unit_id,
            'openVisitLocationId' => $openShift?->openVisit?->location_id,
            'checkingUnit' => $checkingUnit,
            'clockPointUnitCheckList' => $clockPointUnitCheckList,
            'clockPointUnitCheckListItems' => $clockPointUnitCheckListItems,
            'clockPointRoundTask' => $clockPointRoundTask,
            'clockPointRoundProgress' => $clockPointRoundProgress,
            'clockPointIsNextStop' => $clockPointIsNextStop,
            'completingTaskId' => $this->completingTaskId,
            'tasks' => $tasks,
            'hasTimeModule' => $hasTimeModule,
            'evacuationList' => $evacuationList,
            'gpsOnClock' => $this->tenantRequestsClockGps(),
            'gpsVisits' => $gpsVisits,
            'canPunch' => ClockPointScanGrant::isValid($this->clockPointId),
            'teamWorkers' => $teamWorkers,
            'manageWorkersMessage' => $this->manageWorkersMessage,
            'roster' => $roster,
            'hours' => $hours,
            'hoursMonthLabel' => $hoursMonthLabel,
            'hoursIsCurrentMonth' => $hoursIsCurrentMonth,
            'schedule' => $schedule,
            'scheduleMonthLabel' => $scheduleMonthLabel,
            'scheduleUnreadCount' => $scheduleUnreadCount,
            'absenceRequests' => $absenceRequests,
            'showClockPointName' => ! TimePortalData::isGenericClockPointName($this->clockPointName),
            'offerHomescreenShortcut' => $this->clockPointOffersHomescreenShortcut(),
            'isTimePortal' => true,
            'isTeamPortal' => false,
        ]);
    }

    private function requirePunchScanGrant(): bool
    {
        if (ClockPointScanGrant::isValid($this->clockPointId)) {
            return true;
        }

        $this->flashMessage = __('time.portal.errors.scan_required');

        return false;
    }

    private function tenantRequiresPin(): bool
    {
        return TimePortalData::tenantRequiresPin($this->tenantId);
    }

    private function tenantRequestsClockGps(): bool
    {
        return TimePortalData::tenantRequestsClockGps($this->tenantId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function todayDestinationsForView(
        ?Worker $worker,
        mixed $openShift,
        ListWorkDestinationsForWorkerAction $listDestinations,
    ): array {
        if ($worker === null || $openShift === null || ! TimePortalData::tenantAllowsGpsWorkVisits($this->tenantId)) {
            return [];
        }

        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return [];
        }

        try {
            $groups = $this->groupTodayDestinationsByLocation(array_map(
                fn ($row) => $row->toArray(),
                $listDestinations->handle($tenant, $worker),
            ));
        } catch (InvalidArgumentException) {
            return [];
        }

        $visitLocationId = $openShift?->openVisit?->location_id;
        if ($visitLocationId === null) {
            return $groups;
        }

        foreach ($groups as &$group) {
            $onSite = (int) $group['location_id'] === (int) $visitLocationId;
            $group['on_site'] = $onSite;
            foreach ($group['units'] as &$unit) {
                $unit['on_site'] = $onSite;
            }
            unset($unit);
        }
        unset($group);

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{
     *     location_id: int,
     *     location_name: string,
     *     address_line: string,
     *     location_maps_url: string,
     *     location_can_start: bool,
     *     location_pin_latitude: ?float,
     *     location_pin_longitude: ?float,
     *     radius_meters: int,
     *     units: list<array<string, mixed>>
     * }>
     */
    private function groupTodayDestinationsByLocation(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $locationId = (int) $row['location_id'];
            if (! isset($groups[$locationId])) {
                $groups[$locationId] = [
                    'location_id' => $locationId,
                    'location_name' => (string) $row['location_name'],
                    'address_line' => (string) $row['address_line'],
                    'location_maps_url' => (string) ($row['location_maps_url'] ?? $row['maps_url'] ?? ''),
                    'location_can_start' => (bool) ($row['location_can_start'] ?? false),
                    'location_pin_latitude' => $row['location_pin_latitude'] ?? null,
                    'location_pin_longitude' => $row['location_pin_longitude'] ?? null,
                    'radius_meters' => (int) ($row['radius_meters'] ?? 0),
                    'on_site' => false,
                    'units' => [],
                ];
            }

            $group = &$groups[$locationId];
            $locationMapsUrl = trim((string) ($row['location_maps_url'] ?? ''));
            if ($locationMapsUrl !== '') {
                $group['location_maps_url'] = $locationMapsUrl;
            }
            if ($row['location_can_start'] ?? false) {
                $group['location_can_start'] = true;
                $group['location_pin_latitude'] = $row['location_pin_latitude'] ?? $group['location_pin_latitude'];
                $group['location_pin_longitude'] = $row['location_pin_longitude'] ?? $group['location_pin_longitude'];
            }
            if ((int) ($row['radius_meters'] ?? 0) > 0) {
                $group['radius_meters'] = (int) $row['radius_meters'];
            }

            $unitName = trim((string) ($row['unit_name'] ?? ''));
            if ($unitName !== '') {
                $group['units'][] = $row;
            }
            unset($group);
        }

        return array_values($groups);
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
     * @return array{0: ?WorkerDevice, 1: string}
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

    private function parseVisitGps(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function flashVisitError(InvalidArgumentException $e): bool
    {
        $message = match ($e->getMessage()) {
            'visit_gps_required', 'visit_gps_invalid' => __('time.portal.errors.visit_gps_required'),
            'visit_unit_out_of_range', 'unit_visit_pin_missing', 'unit_inactive' => __('time.portal.errors.visit_unit_out_of_range'),
            'visit_already_open' => __('time.portal.errors.visit_already_open'),
            'visit_not_open' => __('time.portal.errors.visit_not_open'),
            'shift_not_open' => __('time.portal.errors.not_clocked_in'),
            'gps_visits_disabled' => __('time.portal.errors.gps_visits_disabled'),
            'worker_location_not_allowed' => __('time.portal.errors.visit_unit_out_of_range'),
            'clock_point_task_read_only' => __('portal.team.read_only_hint'),
            'clock_point_visit_required' => __('portal.team.complete_needs_visit'),
            'clock_point_visit_location_mismatch' => __('portal.worker.errors.not_this_location'),
            default => null,
        };

        if ($message === null) {
            return $this->flashClockDeviceError($e);
        }

        $this->flashMessage = $message;

        return true;
    }

    private function flashClockDeviceError(InvalidArgumentException $e): bool
    {
        if (ClockDeviceRefusalReason::tryFrom($e->getMessage()) === null) {
            return false;
        }

        $this->flashMessage = __('time.portal.errors.device_mismatch');

        return true;
    }

    private function findClockPointTask(Worker $worker, int $taskId): ?Task
    {
        $task = Task::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('internal_team_id', $worker->internal_team_id)
            ->whereIn('status', TaskStatus::openValues())
            ->with(['issue.esgIndicator.translations', 'issue.location', 'issue.unit', 'issue.roundStops'])
            ->find($taskId);

        if ($task === null || $task->issue?->isInspectionRound()) {
            return null;
        }

        return $task;
    }

    private function findClockPointRoundTask(Worker $worker, int $taskId): ?Task
    {
        $task = Task::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('internal_team_id', $worker->internal_team_id)
            ->whereIn('status', TaskStatus::openValues())
            ->with(['issue.roundStops.unit.location', 'roundStopSkips'])
            ->find($taskId);

        if ($task === null || ! $task->issue?->isInspectionRound()) {
            return null;
        }

        return $task;
    }

    private function findClockPointUnit(Worker $worker, int $unitId): ?Unit
    {
        return Unit::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('is_active', true)
            ->with(['location', 'category'])
            ->find($unitId);
    }

    private function resetClockPointUnitCheckForm(): void
    {
        $this->checkResult = '';
        $this->checkLatitude = null;
        $this->checkLongitude = null;
        $this->checkCheckedAt = null;
        $this->checkChecklistItems = [];
        $this->checkDescription = '';
        $this->checkPhotos = [];
        $this->resetErrorBag(['checkResult', 'checkLatitude', 'checkLongitude', 'checkCheckedAt', 'checkChecklistItems', 'checkDescription', 'checkPhotos']);
    }

    private function resetClockPointEsgFields(): void
    {
        $this->completingEsgValueNumeric = null;
        $this->completingEsgValueBoolean = null;
        $this->completingEsgValueString = '';
        $this->completingEsgValueJson = '';
        $this->completingEsgValueMultiChoice = [];
        $this->completingRecordedAt = null;
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

    private function clockPointOffersHomescreenShortcut(): bool
    {
        return (bool) $this->activeClockPoint()?->homescreen_shortcut;
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

    private function hoursMonthStart(): ?Carbon
    {
        $month = $this->hoursMonth !== '' ? $this->hoursMonth : now()->format('Y-m');
        $validator = validator(['month' => $month], ListWorkerHoursRequest::rulesFor());
        if ($validator->fails()) {
            return now()->startOfMonth();
        }

        $parsed = Carbon::createFromFormat('!Y-m', $month);

        return $parsed instanceof Carbon ? $parsed->startOfMonth() : now()->startOfMonth();
    }

    private function deviceForWorker(Worker $worker): ?WorkerDevice
    {
        $token = WorkerDeviceSession::deviceTokenFromRequest();
        if ($token === '') {
            return null;
        }

        return $worker->devices()->where('device_token', $token)->first();
    }

    private function addFailedSignInError(InternalTeam $team): void
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

    protected function portalReleaseTeam(): ?InternalTeam
    {
        return $this->verifiedWorker()?->team;
    }

    protected function portalReleaseFlash(string $message): void
    {
        $this->flashMessage = $message;
    }

    protected function portalManageWorkersTeam(): ?InternalTeam
    {
        return $this->verifiedWorker()?->team;
    }

    protected function portalManageWorkersFlash(string $message): void
    {
        $this->flashMessage = $message;
    }
}

<?php

namespace App\Livewire\Public;

use App\Actions\Public\SubmitReportAction;
use App\Exceptions\Public\PublicReportRateLimitExceededException;
use App\Actions\Units\DeleteUnitBackgroundPhotoAction;
use App\Actions\Units\UpdateUnitBackgroundPhotoAction;
use App\Actions\Units\RecordUnitGpsReportAction;
use App\Actions\QrCodes\StoreQrLinkPhotosAction;
use App\Actions\Tasks\CompleteTaskAction;
use App\Actions\Tasks\StartTaskAction;
use App\Livewire\Concerns\PortalTeamleaderRelease;
use App\Livewire\Concerns\SwitchesPortalUiTheme;
use App\Http\Requests\Public\CompletePortalTaskRequest;
use App\Http\Requests\Public\ReportIssueRequest;
use App\Http\Requests\Public\UpdateUnitPortalPhotosRequest;
use App\Http\Requests\Public\UploadUnitBackgroundPhotoRequest;
use App\Data\Units\RecordUnitGpsReportData;
use App\Http\Requests\Units\RecordUnitGpsReportRequest;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Worker;
use App\Support\Portal\PortalAccess;
use App\Support\Portal\UnitPortalData;
use App\Support\Portal\UnitSignInPhase;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerIcon;
use App\Support\Portal\WorkerIconGuard;
use App\Support\Portal\WorkerVerification;
use App\Support\ResolveAppLocale;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class UnitPortal extends Component
{
    use PortalTeamleaderRelease;
    use SwitchesPortalUiTheme;
    use WithFileUploads;

    public string $token;
    public int $unitId;
    public int $tenantId;
    public ?int $teamId = null;
    public string $tenantName = '';
    public string $locationName = '';
    public string $unitName = '';
    public string $unitDescription = '';

    public string $locale = 'nl';
    public ?string $inactiveReasonKey = null;

    public string $portalSection = 'home';
    public ?int $selectedIssueId = null;

    public string $description = '';
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    public string $reporter_first_name = '';
    public string $reporter_last_name = '';
    public string $reporter_email = '';

    public string $first_name = '';
    public string $last_name = '';
    public string $sign_in_icon_slug = '';

    public ?int $completingTaskId = null;
    public string $completingNote = '';
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $completingPhotos = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPortalPhotos = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $backgroundPhoto = [];

    public string $flashMessage = '';

    public ?float $gpsLatitude = null;
    public ?float $gpsLongitude = null;
    public ?string $gpsReportedAt = null;

    public function mount(string $token): void
    {
        $unit = Unit::withoutGlobalScope('tenant')
            ->with(['location', 'category', 'tenant', 'qrCodes' => fn ($q) => $q->where('status', \App\Enums\QrCodeStatus::Active)])
            ->where('qr_token', $token)
            ->first();

        abort_unless($unit, 404);

        $this->token = $token;
        $this->unitId = $unit->id;
        $this->tenantId = $unit->tenant_id;

        // Get team from category
        $this->teamId = null;
        if ($unit->category !== null) {
            $firstTeam = $unit->category->teams()->first();
            $this->teamId = $firstTeam?->id;
        }

        $this->tenantName = $unit->tenant?->name ?? '';
        $this->unitName = $unit->name;
        $this->locationName = $unit->location?->name ?? '';
        $this->unitDescription = $unit->description ?? '';

        Tenancy::actAs($this->tenantId);

        $this->inactiveReasonKey = PortalAccess::unitPortalInactiveReasonKey($unit);
        $this->syncLocaleFromRequest();
        $this->bootstrapFieldWorker($unit);
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
        app()->setLocale($locale);
    }

    public function openSection(string $section): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        if ($section === 'new' && ! $this->showNewReportSection()) {
            return;
        }

        if (! in_array($section, ['home', 'new', 'issues', 'issue_detail', 'documents', 'announcements'], true)) {
            return;
        }

        if ($section !== 'issue_detail') {
            $this->selectedIssueId = null;
        }

        $this->portalSection = $section;
        $this->flashMessage = '';

        if ($section === 'new') {
            $this->dispatch('wp-prepare-photo-inputs');
        }
    }

    public function openIssueDetail(int $issueId): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        $issue = UnitPortalData::findActiveIssueForUnit($this->unit(), $issueId);
        if ($issue === null || ! $issue->isApproved()) {
            return;
        }

        $this->selectedIssueId = $issue->id;
        $this->portalSection = 'issue_detail';
        $this->sign_in_icon_slug = '';
        $this->cancelCompleteTask();
        $this->resetErrorBag('sign_in_icon_slug');
    }

    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            array_splice($this->photos, $index, 1);
        }
    }

    public function submitReport(SubmitReportAction $submitReport): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        if (! $this->showNewReportSection()) {
            return;
        }

        $this->description = trim($this->description);
        $this->reporter_first_name = trim($this->reporter_first_name);
        $this->reporter_last_name = trim($this->reporter_last_name);
        $this->reporter_email = trim($this->reporter_email);

        $this->validate(ReportIssueRequest::portalRules(), ReportIssueRequest::validationMessages());

        try {
            $submitReport->handle(
                $this->unit(),
                ReportIssueRequest::issueDataFromInput([
                    'description' => $this->description,
                    'reporter_first_name' => $this->reporter_first_name,
                    'reporter_last_name' => $this->reporter_last_name,
                    'reporter_email' => $this->reporter_email,
                ]),
                $this->photos,
                $this->authorizedWorker(),
                request()->ip(),
            );
        } catch (PublicReportRateLimitExceededException $exception) {
            $this->addError('description', $this->rateLimitMessage($exception));

            return;
        }

        $this->reset(['description', 'photos', 'reporter_first_name', 'reporter_last_name', 'reporter_email']);
        $this->dispatch('wp-clear-photo-previews');
        $this->flashMessage = __('portal.report.sent');
        $this->portalSection = 'issues';
    }

    private function rateLimitMessage(PublicReportRateLimitExceededException $exception): string
    {
        $seconds = max(1, $exception->retryAfterSeconds);
        $minutes = max(1, (int) ceil($seconds / 60));

        if ($exception->reason === PublicReportRateLimitExceededException::REASON_COOLDOWN) {
            return __('portal.report.errors.cooldown', ['seconds' => $seconds, 'minutes' => $minutes]);
        }

        return __('portal.report.errors.rate_limited', ['minutes' => $minutes, 'max' => $exception->maxAttempts]);
    }

    public function identifyWorker(): void
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return;
        }

        $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
        ], [
            'first_name.required' => __('portal.worker.errors.name_required'),
            'last_name.required' => __('portal.worker.errors.name_required'),
        ]);

        $identity = WorkerDeviceSession::resolveIdentityOnTeam($team, $this->first_name, $this->last_name);

        if (in_array($identity['status'], ['not_found', 'claimable'], true)) {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));

            return;
        }

        if ($identity['status'] === 'ambiguous') {
            $this->addError('identify', __('portal.worker.errors.identify_ambiguous'));

            return;
        }

        $worker = $identity['worker'] ?? null;
        if ($worker === null || ! WorkerDeviceSession::workerCanActOnUnit($worker, $this->unit())) {
            $this->addError('identify', __('portal.worker.errors.identify_unknown'));

            return;
        }

        WorkerDeviceSession::bindRememberedWorker($team, $worker);
        $this->sign_in_icon_slug = '';
        $this->resetErrorBag(['identify', 'sign_in_icon_slug']);
    }

    public function signInAsDifferentWorker(): void
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return;
        }

        WorkerDeviceSession::revokeDeviceSessionFromRequest($team);
        WorkerIconGuard::clearSessionForTeam((int) $team->id);
        WorkerVerification::clearForTeam((int) $team->id);
        WorkerVerification::clearUnitFieldTrustForTeam((int) $team->id);

        $this->first_name = '';
        $this->last_name = '';
        $this->sign_in_icon_slug = '';
        $this->resetErrorBag(['identify', 'sign_in_icon_slug']);
    }

    public function signInWithIcon(): void
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return;
        }

        $deviceWorker = WorkerDeviceSession::rememberedWorkerOnTeam($team);
        if ($deviceWorker === null) {
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

        if ($worker === null || ! WorkerDeviceSession::workerCanActOnUnit($worker, $this->unit())) {
            WorkerIconGuard::recordFailedAttempt($team);
            $this->sign_in_icon_slug = '';
            $this->addFailedSignInError($team);

            return;
        }

        WorkerDeviceSession::bindRememberedWorker($team, $worker);
        WorkerVerification::establishUnitFieldTrust($team, $worker);
        $this->sign_in_icon_slug = '';
        $this->flashMessage = __('portal.worker.signed_in');
    }

    public function startTask(int $taskId, StartTaskAction $startTask): void
    {
        $worker = $this->authorizedWorker();
        $task = $worker !== null ? $this->findUnitTask($taskId) : null;
        if ($task === null) {
            return;
        }

        $startTask->handle($task, $worker);
        $this->cancelCompleteTask();
        $this->flashMessage = __('portal.worker.task_started');
    }

    public function beginCompleteTask(int $taskId): void
    {
        $worker = $this->authorizedWorker();
        $task = $worker !== null ? $this->findUnitTask($taskId) : null;
        if ($task === null || ! $task->canComplete()) {
            return;
        }

        $this->completingTaskId = $task->id;
        $this->completingNote = '';
        $this->completingPhotos = [];
        $this->dispatch('wp-prepare-photo-inputs');
    }

    public function cancelCompleteTask(): void
    {
        $this->completingTaskId = null;
        $this->completingNote = '';
        $this->completingPhotos = [];
        $this->dispatch('wp-clear-photo-previews');
    }

    public function removeCompletingPhoto(int $index): void
    {
        if (isset($this->completingPhotos[$index])) {
            array_splice($this->completingPhotos, $index, 1);
        }
    }

    public function removeNewPortalPhoto(int $index): void
    {
        if (! $this->workerBelongsToUnitTeam()) {
            return;
        }

        if (isset($this->newPortalPhotos[$index])) {
            array_splice($this->newPortalPhotos, $index, 1);
        }
    }

    public function removeUnitPhoto(int $photoId, \App\Actions\QrCodes\DeleteQrLinkPhotoAction $delete): void
    {
        if (! $this->workerBelongsToUnitTeam()) {
            return;
        }

        $photo = \App\Models\QrLinkPhoto::where('id', $photoId)
            ->where('unit_id', (int) $this->unitId)
            ->first();

        if ($photo === null) {
            return;
        }

        $delete->handle($photo, actorUserId: null);
    }

    public function updateUnitPhotos(StoreQrLinkPhotosAction $storePhotos): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        if (! $this->workerBelongsToUnitTeam()) {
            $this->addError('newPortalPhotos', __('portal.worker.errors.no_permission'));

            return;
        }

        $unit = $this->unit();
        $slotsLeft = max(0, 4 - $unit->qrLinkPhotos()->count());

        $this->validate(
            UpdateUnitPortalPhotosRequest::ruleSet($slotsLeft),
            UpdateUnitPortalPhotosRequest::validationMessages(),
        );

        if (empty($this->newPortalPhotos)) {
            return;
        }
        $unit->loadMissing(['qrCodes' => fn ($q) => $q->where('status', \App\Enums\QrCodeStatus::Active)]);
        $qrCode = $unit->qrCodes->first();

        try {
            $storePhotos->handle(
                unit: $unit,
                qrCode: $qrCode,
                photos: $this->newPortalPhotos,
                actorUserId: null,
            );
        } catch (\Throwable $e) {
            $this->addError('newPortalPhotos', __('portal.report.errors.photos_failed'));

            return;
        }

        $this->reset('newPortalPhotos');
        $this->dispatch('wp-clear-photo-previews');
        $this->flashMessage = __('portal.unit.photos_updated');
    }

    public function updateUnitGps(): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        if (! $this->canCaptureUnitGps()) {
            $this->addError('gpsLatitude', __('portal.worker.errors.no_permission'));

            return;
        }

        $this->validate(
            RecordUnitGpsReportRequest::portalRuleSet(),
            RecordUnitGpsReportRequest::portalValidationMessages(),
        );

        $reportedAtValidator = \Illuminate\Support\Facades\Validator::make(
            ['gpsReportedAt' => $this->gpsReportedAt],
            ['gpsReportedAt' => ['required', 'date']],
        );
        RecordUnitGpsReportRequest::assertPortalReportedAt((string) $this->gpsReportedAt, $reportedAtValidator);
        if ($reportedAtValidator->fails()) {
            foreach ($reportedAtValidator->errors()->getMessages() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $unit = $this->unit();
        $worker = $this->authorizedWorker();

        app(RecordUnitGpsReportAction::class)->handle(
            $unit,
            new RecordUnitGpsReportData(
                latitude: (float) $this->gpsLatitude,
                longitude: (float) $this->gpsLongitude,
                reportedAt: \Carbon\CarbonImmutable::parse((string) $this->gpsReportedAt),
            ),
            $this->tenantId,
            null,
            $worker?->id,
        );

        $this->reset('gpsLatitude', 'gpsLongitude', 'gpsReportedAt');
        $this->flashMessage = __('portal.unit.gps_updated');
    }

    public function removeBackgroundPhoto(int $index): void
    {
        if (! $this->workerBelongsToUnitTeam()) {
            return;
        }

        if (isset($this->backgroundPhoto[$index])) {
            array_splice($this->backgroundPhoto, $index, 1);
        }
    }

    public function uploadBackgroundPhoto(UpdateUnitBackgroundPhotoAction $action): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        if (! $this->workerBelongsToUnitTeam()) {
            $this->addError('backgroundPhoto', __('portal.worker.errors.no_permission'));

            return;
        }

        $this->validate(
            UploadUnitBackgroundPhotoRequest::ruleSet(),
            UploadUnitBackgroundPhotoRequest::validationMessages(),
        );

        if (empty($this->backgroundPhoto)) {
            return;
        }

        $action->handle($this->unit(), $this->backgroundPhoto[0], null);

        $this->reset('backgroundPhoto');
        $this->dispatch('wp-clear-photo-previews');
        $this->flashMessage = __('portal.unit.background_photo_updated');
    }

    public function deleteUnitBackgroundPhoto(DeleteUnitBackgroundPhotoAction $action): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        if (! $this->workerBelongsToUnitTeam()) {
            $this->addError('backgroundPhoto', __('portal.worker.errors.no_permission'));

            return;
        }

        $action->handle($this->unit(), null);
        $this->flashMessage = __('portal.unit.background_photo_deleted');
    }

    public function submitCompleteTask(CompleteTaskAction $completeTask): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null || $this->completingTaskId === null) {
            return;
        }

        $this->validate(
            CompletePortalTaskRequest::ruleSet(),
            CompletePortalTaskRequest::validationMessages(),
        );

        $task = $this->findUnitTask($this->completingTaskId);
        if ($task === null) {
            return;
        }

        $completeTask->handle($task, $worker, $this->completingNote, $this->completingPhotos);

        $this->cancelCompleteTask();
        $this->selectedIssueId = null;
        $this->portalSection = 'task_done';
        $this->flashMessage = '';
    }

    public function render()
    {
        $unit = $this->unit();
        app()->setLocale($this->locale);

        $team = $this->activeTeam();
        $worker = $this->authorizedWorker();
        $canAct = $worker !== null;
        $hasUnitTeam = $team !== null;

        $deviceWorker = (! $canAct && $team !== null)
            ? WorkerDeviceSession::rememberedWorkerOnTeam($team)
            : null;
        $anyDeviceWorker = ! $canAct ? WorkerDeviceSession::workerFromDeviceCookie() : null;
        $iconBlocked = (! $canAct && $team !== null) ? WorkerIconGuard::isBlocked($team) : false;

        $phase = UnitSignInPhase::resolvePhase(
            $canAct,
            $hasUnitTeam,
            UnitSignInPhase::activeWorkerCountOnTeam($team),
            $iconBlocked,
            $deviceWorker,
            $anyDeviceWorker,
        );
        $isFieldVisitor = UnitSignInPhase::isFieldWorkerVisitor($canAct, $deviceWorker);

        $issues = collect();
        $documents = collect();
        $announcements = collect();
        $selectedIssue = null;
        $openTasksForIssue = collect();
        $allOpenUnitTasks = collect();
        $openTaskCount = 0;

        if ($this->inactiveReasonKey === null) {
            if (in_array($this->portalSection, ['home', 'issues', 'issue_detail'], true)) {
                $issues = UnitPortalData::activeIssuesForUnit($unit);
            }
            if (in_array($this->portalSection, ['home', 'documents'], true)) {
                $documents = UnitPortalData::activeDocumentsForUnit($unit);
            }
            if (in_array($this->portalSection, ['home', 'announcements'], true)) {
                $announcements = UnitPortalData::activeAnnouncementsForUnit($unit);
            }

            if ($team !== null) {
                if ($this->portalSection === 'home') {
                    $openTaskCount = UnitPortalData::openUnitTaskCount($unit, (int) $team->id);
                }
                if ($isFieldVisitor) {
                    $allOpenUnitTasks = UnitPortalData::allOpenUnitTasks($unit, (int) $team->id);
                }
            }

            if ($this->selectedIssueId !== null) {
                $selectedIssue = UnitPortalData::findActiveIssueForUnit($unit, $this->selectedIssueId);
                if ($selectedIssue === null || ! $selectedIssue->isApproved()) {
                    $this->selectedIssueId = null;
                    $selectedIssue = null;
                    if ($this->portalSection === 'issue_detail') {
                        $this->portalSection = 'issues';
                    }
                } elseif ($team !== null) {
                    $openTasksForIssue = UnitPortalData::openTeamTasksForIssue($selectedIssue, (int) $team->id);
                }
            }
        }

        return view('livewire.public.unit-portal', [
            'canAct' => $canAct,
            'showNewReportSection' => $this->showNewReportSection(),
            'worker' => $worker,
            'team' => $team,
            'phase' => $phase,
            'isFieldVisitor' => $isFieldVisitor,
            'deviceWorker' => $deviceWorker,
            'iconBlocked' => $iconBlocked,
            'remainingAttempts' => $team !== null ? WorkerIconGuard::remainingAttempts($team) : WorkerIconGuard::MAX_FAILED_ATTEMPTS,
            'issues' => $issues,
            'documents' => $documents,
            'announcements' => $announcements,
            'selectedIssue' => $selectedIssue,
            'openTasksForIssue' => $openTasksForIssue,
            'allOpenUnitTasks' => $allOpenUnitTasks,
            'openTaskCount' => $openTaskCount,
            'hasUnitTeam' => $team !== null,
            'qrLinkPhotos' => $unit->qrLinkPhotos ?? collect(),
            'workerBelongsToUnitTeam' => $worker !== null && $unit->category !== null && $unit->category->teams()->where('internal_teams.id', $worker->internal_team_id)->exists(),
            'canCaptureUnitGps' => $worker !== null || ($unit->category?->allow_gps_location ?? false),
            'mapsUrl' => $unit->googleMapsUrl(),
            'isTeamPortal' => false,
            'manageWorkersMessage' => '',
            'unitBackgroundUrl' => $unit->backgroundPhotoPublicUrl(),
        ])->layout('components.layouts.public', [
            'portalBgUrl' => $unit->backgroundPhotoPublicUrl(),
            'title' => 'WinProx',
        ]);
    }

    private function unit(): Unit
    {
        return Unit::with(['location', 'category', 'qrLinkPhotos', 'latestGpsReport'])->findOrFail($this->unitId);
    }

    private function activeTeam(): ?\App\Models\InternalTeam
    {
        if ($this->inactiveReasonKey !== null) {
            return null;
        }

        $cookieWorker = WorkerDeviceSession::workerFromDeviceCookie();
        if ($cookieWorker !== null && $cookieWorker->internal_team_id !== null) {
            $team = $cookieWorker->team;
            if ($team !== null && $team->is_active) {
                return $team;
            }
        }

        $unit = $this->unit();
        if ($unit->category === null) {
            return null;
        }

        $team = $unit->category->teams()->first();

        return ($team !== null && $team->is_active) ? $team : null;
    }

    private function authorizedWorker(): ?Worker
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return null;
        }

        $worker = WorkerVerification::verifiedWorker($team);
        if ($worker === null) {
            return null;
        }

        if (! WorkerDeviceSession::workerCanActOnUnit($worker, $this->unit())) {
            WorkerVerification::clearForTeam((int) $team->id);
            WorkerVerification::clearUnitFieldTrustForTeam((int) $team->id);

            return null;
        }

        return $worker;
    }

    private function showNewReportSection(): bool
    {
        $unit = $this->unit();

        if ($unit->public_reports_enabled) {
            return true;
        }

        return $this->authorizedWorker() !== null;
    }

    private function canCaptureUnitGps(): bool
    {
        if ($this->authorizedWorker() !== null) {
            return true;
        }

        $unit = $this->unit();

        return (bool) ($unit->category?->allow_gps_location ?? false);
    }

    private function workerBelongsToUnitTeam(): bool
    {
        $worker = $this->authorizedWorker();
        if ($worker === null) {
            return false;
        }

        $unit = $this->unit();
        if ($unit->category === null) {
            return false;
        }

        return $unit->category->teams()->where('internal_teams.id', $worker->internal_team_id)->exists();
    }

    private function findUnitTask(int $taskId): ?Task
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return null;
        }

        return Task::where('internal_team_id', $team->id)
            ->whereHas('issue', fn ($q) => $q->where('unit_id', $this->unitId))
            ->find($taskId);
    }

    private function bootstrapFieldWorker(Unit $unit): void
    {
        $team = $this->activeTeam();
        if ($team === null) {
            return;
        }

        WorkerVerification::restoreFromUnitFieldTrust($team, $unit);

        if (WorkerVerification::verifiedWorker($team) !== null) {
            return;
        }

        $worker = WorkerDeviceSession::rememberedWorkerOnTeam($team);
        if ($worker !== null) {
            WorkerVerification::markVerified($team, $worker);
        }
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
        $worker = $this->authorizedWorker();

        return ($worker !== null && $worker->is_teamleader) ? $worker : null;
    }

    protected function portalReleaseTeam(): ?\App\Models\InternalTeam
    {
        return $this->activeTeam();
    }

    protected function portalReleaseFlash(string $message): void
    {
        $this->flashMessage = $message;
    }
}

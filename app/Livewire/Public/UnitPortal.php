<?php

namespace App\Livewire\Public;

use App\Actions\Public\SubmitReportAction;
use App\Actions\Tasks\CompleteTaskAction;
use App\Actions\Tasks\StartTaskAction;
use App\Livewire\Concerns\PortalTeamleaderRelease;
use App\Http\Requests\Public\ReportIssueRequest;
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
    use WithFileUploads;

    public string $token;
    public int $unitId;
    public int $tenantId;
    public ?int $teamId = null;
    public string $locationName = '';
    public string $unitName = '';

    public string $locale = 'nl';
    public ?string $inactiveReasonKey = null;

    public string $portalSection = 'home';
    public ?int $selectedIssueId = null;

    public string $description = '';
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    public string $first_name = '';
    public string $last_name = '';
    public string $sign_in_icon_slug = '';

    public ?int $completingTaskId = null;
    public string $completingNote = '';
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $completingPhotos = [];

    public string $flashMessage = '';

    public function mount(string $token): void
    {
        $unit = Unit::withoutGlobalScope('tenant')
            ->with(['location', 'defaultInternalTeam'])
            ->where('qr_token', $token)
            ->first();

        abort_unless($unit, 404);

        $this->token = $token;
        $this->unitId = $unit->id;
        $this->tenantId = $unit->tenant_id;
        $this->teamId = $unit->default_internal_team_id;
        $this->unitName = $unit->name;
        $this->locationName = $unit->location?->name ?? '';

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
        Cookie::queue('locale', $locale, 60 * 24 * 365);
        $this->locale = $locale;
        app()->setLocale($locale);
    }

    public function openSection(string $section): void
    {
        if ($this->inactiveReasonKey !== null) {
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

        $this->description = trim($this->description);
        $request = new ReportIssueRequest;
        $this->validate($request->rules(), $request->messages());

        $submitReport->handle($this->unit(), ['description' => $this->description], $this->photos);

        $this->reset(['description', 'photos']);
        $this->dispatch('wp-clear-photo-previews');
        $this->flashMessage = __('portal.report.sent');
        $this->portalSection = 'issues';
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

        $startTask->handle($task);
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

    public function submitCompleteTask(CompleteTaskAction $completeTask): void
    {
        $worker = $this->authorizedWorker();
        if ($worker === null || $this->completingTaskId === null) {
            return;
        }

        $this->validate([
            'completingNote' => ['nullable', 'string', 'max:2000'],
            'completingPhotos' => ['nullable', 'array', 'max:4'],
            'completingPhotos.*' => ['image', 'max:10240'],
        ], [
            'completingNote.max' => __('portal.worker.errors.note_max'),
            'completingPhotos.max' => __('portal.report.errors.photos_max'),
            'completingPhotos.*.image' => __('portal.report.errors.photos_image'),
            'completingPhotos.*.max' => __('portal.report.errors.photos_size'),
        ]);

        $task = $this->findUnitTask($this->completingTaskId);
        if ($task === null) {
            return;
        }

        $completeTask->handle($task, $worker, $this->completingNote, $this->completingPhotos);

        $this->cancelCompleteTask();
        $this->selectedIssueId = null;
        $this->portalSection = 'issues';
        $this->flashMessage = __('portal.worker.task_completed');
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

            if ($isFieldVisitor && $team !== null) {
                $allOpenUnitTasks = UnitPortalData::allOpenUnitTasks($unit, (int) $team->id);
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
        ]);
    }

    private function unit(): Unit
    {
        return Unit::with(['location', 'defaultInternalTeam'])->findOrFail($this->unitId);
    }

    private function activeTeam(): ?\App\Models\InternalTeam
    {
        if ($this->inactiveReasonKey !== null) {
            return null;
        }

        $team = $this->unit()->defaultInternalTeam;

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

        $worker = WorkerDeviceSession::workerOnTeamFromDeviceCookie($team);
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
            $this->locale = $lang;
        } else {
            $this->locale = app()->getLocale();
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

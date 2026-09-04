<?php

namespace App\Livewire\Pages;

use App\Actions\Communication\ImportInternalTeamTranslationsAction;
use App\Actions\Communication\ImportUnitCheckListTranslationsAction;
use App\Actions\Workers\DeleteWorkerImportBatchAction;
use App\Actions\Workers\ImportWorkersAction;
use App\Data\Workers\DeleteWorkerImportBatchData;
use App\Data\Workers\ImportWorkersData;
use App\Http\Requests\Workers\ImportWorkersRequest;
use App\Actions\Team\CreateColleagueAction;
use App\Actions\Team\CreateTeamAction;
use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\DeleteTeamAction;
use App\Actions\Team\DeleteWorkerAction;
use App\Actions\Team\DeleteWorkerPhotoAction;
use App\Actions\Team\ResetWorkerIconAction;
use App\Actions\Team\SetColleagueActiveAction;
use App\Actions\Team\SetTeamActiveAction;
use App\Actions\Team\SetWorkerActiveAction;
use App\Actions\Team\SetWorkerTeamleaderAction;
use App\Actions\Team\SyncTeamCategoriesAction;
use App\Actions\Team\UpdateColleagueAction;
use App\Actions\Team\UpdateTeamAction;
use App\Actions\Team\UpdateWorkerAction;
use App\Actions\Team\UpdateWorkerPhotoAction;
use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Http\Requests\Team\StoreColleagueRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\StoreWorkerRequest;
use App\Http\Requests\Team\UpdateColleagueRequest;
use App\Http\Requests\Team\UpdateWorkerRequest;
use App\Actions\Units\CopyUnitCheckListFromStarterAction;
use App\Actions\Units\DeactivateUnitCheckListAction;
use App\Actions\Units\DeleteUnitCheckListAction;
use App\Actions\Units\SaveUnitCheckListAction;
use App\Data\Units\SaveUnitCheckListData;
use App\Http\Requests\Units\SaveUnitCheckListRequest;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\UnitCheckList;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\Validator;
use App\Support\Tenancy;
use App\Support\Translation\LocaleSupport;
use App\Support\Workers\WorkerImportBatchRegistry;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Gebruikers-hub: collega-gebruikers (admin), operationele teams en workers.
 * Dun: validatie via Form Requests, mutaties via Actions; RBAC via role + policy.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Team extends Component
{
    use AuthorizesRequests, WithFileUploads;

    // Collega-gebruiker (modal)
    public bool $showColleagueModal = false;
    public ?int $editingColleagueId = null;
    public string $colleagueName = '';
    public string $colleagueEmail = '';
    public string $colleagueLocale = 'nl';
    public string $colleagueRole = User::ROLE_EMPLOYEE;
    public string $colleaguePassword = '';
    public string $colleaguePasswordConfirmation = '';
    public bool $colleagueSendAccountEmail = true;

    public bool $colleagueNotifyOnNewIssueEmail = true;

    /** @var list<int> */
    public array $colleagueLocationIds = [];

    public ?int $colleaguePunchClockTeamId = null;

    // Team (modal)
    public bool $showTeamModal = false;
    public ?int $editingTeamId = null;
    public string $teamName = '';
    public int $teamSortOrder = 0;
    public bool $teamIsActive = true;
    public bool $teamClocksAllLocations = false;
    public string $teamSessionLifespanType = 'daily';
    public ?int $teamSessionLifespanCustomHours = null;
    public string $teamPreviewLocale = '';
    public string $teamTranslationName = '';

    /** @var list<int> */
    public array $selectedCategoryIds = [];

    // Worker toevoegen (inline per team)
    public ?int $addingWorkerTeamId = null;

    /** @var list<int> */
    public array $expandedTeamIds = [];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'worker')]
    public ?int $highlightWorkerId = null;

    #[Url(as: 'team')]
    public ?int $highlightTeamId = null;

    /** Sidebar: backoffice = colleague users; teams = checklists + operational teams. */
    #[Url(as: 'section')]
    public ?string $section = null;

    public string $workerFirstName = '';
    public string $workerLastName = '';
    public string $workerEmail = '';
    public string $workerPhone = '';
    public bool $workerIsExternal = false;
    public string $workerCompanyName = '';
    public string $workerSsin = '';

    /** @var TemporaryUploadedFile|null */
    public $workerPhoto = null;

    public bool $removeWorkerPhoto = false;

    public ?string $existingWorkerPhotoUrl = null;

    /** @var list<int> */
    public array $selectedWorkerLocationIds = [];

    // Worker bewerken/aanmaken (modal)
    public bool $showWorkerModal = false;
    public ?int $editingWorkerId = null;

    // Worker CSV import
    public bool $showWorkerImportModal = false;
    public $workerImportFile = null;
    public array $workerImportErrors = [];
    public ?int $workerImportedCount = null;
    public ?string $workersImportNotice = null;
    public string $workersImportNoticeType = 'success';

    // Unit-check checklists (templates, optional team owner)
    public bool $showCheckListsSection = false;

    public bool $showCheckListModal = false;

    public ?int $editingCheckListId = null;

    public string $checkListName = '';

    public string $checkListItemsText = '';

    public bool $checkListIsActive = true;

    public ?int $checkListTeamId = null;

    public string $checkListPreviewLocale = '';

    public string $checkListTranslationName = '';

    public string $checkListTranslationItemsText = '';

    public function mount(): void
    {
        if ($this->highlightWorkerId !== null) {
            $worker = Worker::query()
                ->where('tenant_id', Tenancy::id())
                ->find($this->highlightWorkerId);

            if ($worker !== null) {
                $this->expandTeam((int) $worker->internal_team_id);
                $this->section = 'teams';
            }
        }

        if ($this->highlightTeamId !== null) {
            $this->expandTeam($this->highlightTeamId);
            $this->section = 'teams';
        }

        if (! in_array($this->section, ['backoffice', 'teams'], true)) {
            $this->section = 'teams';
        }

        if ($this->section === 'backoffice' && ! (auth()->user()?->can('create', User::class) ?? false)) {
            $this->section = 'teams';
        }
    }

    public function isBackofficeSection(): bool
    {
        return $this->section === 'backoffice';
    }

    // --- Collega-gebruikers (alleen admin) --------------------------------

    public function openCreateColleague(): void
    {
        try {
            $this->authorize('create', User::class);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->addError('colleagueCreate', __('team.errors.not_authorized'));
            return;
        }

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        if (! $tenant->canAddSeat()) {
            $this->addError('colleagueCreate', __('team.errors.seat_limit'));
            return;
        }

        $this->resetColleagueForm();
        $this->editingColleagueId = null;
        $this->showColleagueModal = true;
    }

    public function openEditColleague(int $id): void
    {
        $user = User::where('tenant_id', Tenancy::id())->findOrFail($id);
        $this->authorize('update', $user);

        $this->editingColleagueId = $user->id;
        $this->colleagueName = $user->name;
        $this->colleagueEmail = $user->email;
        $this->colleagueLocale = $user->locale ?: config('locales.default', 'nl');
        $this->colleagueRole = $user->role;
        $this->colleaguePassword = '';
        $this->colleaguePasswordConfirmation = '';
        $this->colleagueSendAccountEmail = false;
        $this->colleagueNotifyOnNewIssueEmail = (bool) $user->notify_on_new_issue_email;
        $this->colleagueLocationIds = $user->role === User::ROLE_EMPLOYEE
            ? $user->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all()
            : [];
        $this->colleaguePunchClockTeamId = $user->linkedWorker?->internal_team_id;
        $this->resetErrorBag();
        $this->showColleagueModal = true;
    }

    public function saveColleague(CreateColleagueAction $createColleague, UpdateColleagueAction $updateColleague): void
    {
        if ($this->editingColleagueId !== null) {
            $user = User::where('tenant_id', Tenancy::id())->findOrFail($this->editingColleagueId);
            $this->authorize('update', $user);

            $validated = $this->validateColleagueForUpdate($user->id);

            $updateColleague->handle($user, $validated, (int) auth()->id());
        } else {
            $this->authorize('create', User::class);

            $validated = $this->validateColleagueForCreate();

            try {
                $createColleague->handle($validated, (int) Tenancy::id(), (int) auth()->id());
            } catch (InvalidArgumentException $e) {
                if (in_array($e->getMessage(), ['seat_limit_exceeded', 'user_limit_exceeded'], true)) {
                    $this->addError('colleagueEmail', __('team.errors.seat_limit'));

                    return;
                }

                throw $e;
            }
        }

        $this->showColleagueModal = false;
        $this->resetColleagueForm();
        $this->dispatch('saved');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateColleagueForCreate(): array
    {
        $rules = StoreColleagueRequest::baseRules();
        $messages = StoreColleagueRequest::messageMap();

        $validated = $this->validate(
            [
                'colleagueName' => $rules['name'],
                'colleagueEmail' => $rules['email'],
                'colleagueLocale' => $rules['locale'],
                'colleagueRole' => $rules['role'],
                'colleaguePassword' => $rules['password'],
                'colleaguePasswordConfirmation' => ['required', 'same:colleaguePassword'],
                'colleagueSendAccountEmail' => $rules['send_account_email'],
                'colleagueNotifyOnNewIssueEmail' => $rules['notify_on_new_issue_email'],
            ],
            $this->colleagueValidationMessages($messages),
        );

        return [
            'name' => $validated['colleagueName'],
            'email' => $validated['colleagueEmail'],
            'locale' => $validated['colleagueLocale'],
            'role' => $validated['colleagueRole'],
            'password' => $validated['colleaguePassword'],
            'send_account_email' => (bool) $validated['colleagueSendAccountEmail'],
            'notify_on_new_issue_email' => (bool) $validated['colleagueNotifyOnNewIssueEmail'],
            'location_ids' => $this->colleagueLocationIds,
            'punch_clock_team_id' => $this->colleaguePunchClockTeamId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateColleagueForUpdate(int $userId): array
    {
        $rules = UpdateColleagueRequest::baseRules($userId);
        $messages = (new UpdateColleagueRequest)->messages();

        $validated = $this->validate(
            [
                'colleagueName' => $rules['name'],
                'colleagueEmail' => $rules['email'],
                'colleagueLocale' => $rules['locale'],
                'colleagueRole' => $rules['role'],
                'colleaguePassword' => $rules['password'],
                'colleaguePasswordConfirmation' => ['nullable', 'required_with:colleaguePassword', 'same:colleaguePassword'],
                'colleagueNotifyOnNewIssueEmail' => $rules['notify_on_new_issue_email'],
            ],
            $this->colleagueValidationMessages($messages),
        );

        $payload = [
            'name' => $validated['colleagueName'],
            'email' => $validated['colleagueEmail'],
            'locale' => $validated['colleagueLocale'],
            'role' => $validated['colleagueRole'],
            'notify_on_new_issue_email' => (bool) $validated['colleagueNotifyOnNewIssueEmail'],
        ];

        if ($validated['colleaguePassword'] !== '') {
            $payload['password'] = $validated['colleaguePassword'];
        }

        $payload['location_ids'] = $this->colleagueLocationIds;
        $payload['punch_clock_team_id'] = $this->colleaguePunchClockTeamId;

        return $payload;
    }

    /**
     * @param  array<string, string>  $messages
     * @return array<string, string>
     */
    private function colleagueValidationMessages(array $messages): array
    {
        return [
            'colleagueName.required' => $messages['name.required'] ?? '',
            'colleagueEmail.required' => $messages['email.required'] ?? '',
            'colleagueEmail.email' => $messages['email.email'] ?? '',
            'colleagueEmail.unique' => $messages['email.unique'] ?? '',
            'colleagueLocale.required' => $messages['locale.required'] ?? '',
            'colleagueLocale.in' => $messages['locale.in'] ?? '',
            'colleagueRole.required' => $messages['role.required'] ?? '',
            'colleagueRole.in' => $messages['role.in'] ?? '',
            'colleaguePassword.required' => $messages['password.required'] ?? '',
            'colleaguePassword.min' => $messages['password.min'] ?? '',
            'colleaguePasswordConfirmation.required' => $messages['password_confirmation.required'] ?? '',
            'colleaguePasswordConfirmation.same' => $messages['password_confirmation.same'] ?? '',
        ];
    }

    public function setColleagueActive(int $id, bool $active, SetColleagueActiveAction $setActive): void
    {
        if ($id === auth()->id()) {
            return;
        }

        $user = User::where('tenant_id', Tenancy::id())->findOrFail($id);
        $this->authorize('update', $user);

        try {
            $setActive->handle($user, $active, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'seat_limit_exceeded') {
                $this->addError('colleagueCreate', __('team.errors.seat_limit'));
            }
        }
    }

    public function cancelColleague(): void
    {
        $this->showColleagueModal = false;
        $this->resetColleagueForm();
    }

    private function resetColleagueForm(): void
    {
        $this->reset([
            'colleagueName',
            'colleagueEmail',
            'colleagueLocale',
            'colleagueRole',
            'colleaguePassword',
            'colleaguePasswordConfirmation',
            'colleagueSendAccountEmail',
            'colleagueNotifyOnNewIssueEmail',
            'colleagueLocationIds',
            'colleaguePunchClockTeamId',
            'editingColleagueId',
        ]);
        $this->colleagueLocale = (string) (auth()->user()?->locale ?: config('locales.default', 'nl'));
        $this->colleagueRole = User::ROLE_EMPLOYEE;
        $this->colleagueSendAccountEmail = true;
        $this->colleagueNotifyOnNewIssueEmail = true;
        $this->resetErrorBag();
    }

    // --- Teams -------------------------------------------------------------

    public function openCreateTeam(): void
    {
        Gate::authorize('create', InternalTeam::class);

        $this->resetTeamForm();
        $this->editingTeamId = null;
        $this->showTeamModal = true;
    }

    public function openEditTeam(int $id): void
    {
        $team = InternalTeam::query()->with('translations')->findOrFail($id);
        Gate::authorize('update', $team);

        $this->editingTeamId = $team->id;
        $this->teamName = $team->name;
        $this->teamSortOrder = $team->sort_order;
        $this->teamIsActive = $team->is_active;
        $this->teamClocksAllLocations = (bool) $team->clocks_all_locations;

        // Determine session lifespan type
        if ($team->session_lifespan_hours === 14) {
            $this->teamSessionLifespanType = 'daily';
            $this->teamSessionLifespanCustomHours = null;
        } elseif ($team->session_lifespan_hours === 144) {
            $this->teamSessionLifespanType = 'weekly';
            $this->teamSessionLifespanCustomHours = null;
        } elseif ($team->session_lifespan_hours !== null) {
            $this->teamSessionLifespanType = 'custom';
            $this->teamSessionLifespanCustomHours = $team->session_lifespan_hours;
        } else {
            $this->teamSessionLifespanType = 'daily';
            $this->teamSessionLifespanCustomHours = null;
        }

        $this->selectedCategoryIds = $team->categories()->pluck('categories.id')->toArray();
        $this->teamPreviewLocale = $this->defaultTranslationLocaleForTeam($team);
        $this->hydrateTeamTranslationInput($team);

        $this->resetErrorBag();
        $this->showTeamModal = true;
    }

    public function updatedTeamPreviewLocale(): void
    {
        if ($this->editingTeamId === null) {
            $this->teamTranslationName = '';

            return;
        }

        $team = InternalTeam::query()
            ->with('translations')
            ->find($this->editingTeamId);

        $this->hydrateTeamTranslationInput($team);
    }

    public function saveTeam(CreateTeamAction $createTeam, UpdateTeamAction $updateTeam, SyncTeamCategoriesAction $syncCategories): void
    {
        $request = new StoreTeamRequest;
        $validated = $this->validate(
            [
                'teamName' => $request->rules()['name'],
                'teamSortOrder' => $request->rules()['sort_order'],
            ],
            ['teamName.required' => __('team.errors.team_name_required')],
        );

        // Calculate session_lifespan_hours based on type
        $sessionLifespanHours = null;
        if ($this->teamSessionLifespanType === 'daily') {
            $sessionLifespanHours = 14;
        } elseif ($this->teamSessionLifespanType === 'weekly') {
            $sessionLifespanHours = 144;
        } elseif ($this->teamSessionLifespanType === 'custom' && $this->teamSessionLifespanCustomHours !== null) {
            $sessionLifespanHours = $this->teamSessionLifespanCustomHours;
        }

        if ($this->editingTeamId !== null) {
            $team = InternalTeam::findOrFail($this->editingTeamId);
            Gate::authorize('update', $team);

            // Actief-status wijzigen mag alleen een admin (= deactiveren-recht).
            $active = auth()->user()->can('deactivate', $team) ? $this->teamIsActive : $team->is_active;

            $updateTeam->handle($team, [
                'name' => $validated['teamName'],
                'sort_order' => $this->teamSortOrder,
                'is_active' => $active,
                'clocks_all_locations' => $this->teamClocksAllLocations,
                'session_lifespan_hours' => $sessionLifespanHours,
            ], (int) auth()->id());

            Gate::authorize('syncCategories', $team);
            $syncCategories->handle($team, $this->selectedCategoryIds, (int) auth()->id());
        } else {
            Gate::authorize('create', InternalTeam::class);

            $team = $createTeam->handle([
                'name' => $validated['teamName'],
                'original_language' => auth()->user()?->locale,
                'sort_order' => $this->teamSortOrder,
                'is_active' => $this->teamIsActive,
                'clocks_all_locations' => $this->teamClocksAllLocations,
                'session_lifespan_hours' => $sessionLifespanHours,
            ], (int) Tenancy::id(), (int) auth()->id());

            Gate::authorize('syncCategories', $team);
            $syncCategories->handle($team, $this->selectedCategoryIds, (int) auth()->id());
        }

        $this->showTeamModal = false;
        $this->resetTeamForm();
        $this->dispatch('saved');
    }

    public function saveTeamTranslationOverride(ImportInternalTeamTranslationsAction $importTeamTranslations): void
    {
        if ($this->editingTeamId === null) {
            return;
        }

        $team = InternalTeam::query()
            ->with('translations')
            ->findOrFail($this->editingTeamId);

        Gate::authorize('update', $team);

        if (! $team->is_active) {
            $this->addError('teamTranslationName', __('team.errors.translation_requires_active'));

            return;
        }

        $validated = $this->validate([
            'teamTranslationName' => ['required', 'string', 'max:255'],
        ]);

        $locale = LocaleSupport::normalize($this->teamPreviewLocale);
        if ($locale === $team->normalizedOriginalLanguage()) {
            $this->addError('teamTranslationName', __('issues.errors.translation_same_as_source'));

            return;
        }

        $name = trim((string) $validated['teamTranslationName']);
        if ($name === '') {
            $this->addError('teamTranslationName', __('issues.errors.translation_import_invalid'));

            return;
        }

        try {
            $importTeamTranslations->handle([
                [
                    'internal_team_id' => $team->id,
                    'locale' => $locale,
                    'name' => $name,
                ],
            ], (int) auth()->id());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                if (! is_array($messages)) {
                    continue;
                }

                foreach ($messages as $message) {
                    $this->addError('teamTranslationName', (string) $message);
                }
            }

            return;
        }

        $this->hydrateTeamTranslationInput($team->fresh('translations'));
        session()->flash('success', __('team.teams.flash.translation_saved'));
    }

    public function setTeamActive(int $id, bool $active, SetTeamActiveAction $setActive): void
    {
        $team = InternalTeam::findOrFail($id);
        Gate::authorize('deactivate', $team);

        $setActive->handle($team, $active, (int) auth()->id());
    }

    public function deleteTeam(int $id, DeleteTeamAction $deleteTeam): void
    {
        $team = InternalTeam::findOrFail($id);
        Gate::authorize('delete', $team);

        $deleteTeam->handle($team, (int) auth()->id());

        $this->expandedTeamIds = array_values(array_diff($this->expandedTeamIds, [$id]));

        if ($this->addingWorkerTeamId === $id) {
            $this->cancelWorker();
        }
    }

    public function toggleTeam(int $teamId): void
    {
        $team = InternalTeam::findOrFail($teamId);
        Gate::authorize('update', $team);

        if (in_array($teamId, $this->expandedTeamIds, true)) {
            $this->expandedTeamIds = array_values(array_diff($this->expandedTeamIds, [$teamId]));

            if ($this->addingWorkerTeamId === $teamId) {
                $this->cancelWorker();
            }
        } else {
            $this->expandTeam($teamId);
        }
    }

    public function cancelTeam(): void
    {
        $this->showTeamModal = false;
        $this->resetTeamForm();
    }

    private function resetTeamForm(): void
    {
        $this->reset([
            'teamName',
            'teamSortOrder',
            'teamIsActive',
            'teamClocksAllLocations',
            'teamSessionLifespanType',
            'teamSessionLifespanCustomHours',
            'editingTeamId',
            'selectedCategoryIds',
            'teamPreviewLocale',
            'teamTranslationName',
        ]);
        $this->teamIsActive = true;
        $this->teamClocksAllLocations = false;
        $this->teamSessionLifespanType = 'daily';
        $this->teamSessionLifespanCustomHours = null;
        $this->selectedCategoryIds = [];
        $this->resetErrorBag();
    }

    private function hydrateTeamTranslationInput(?InternalTeam $team): void
    {
        if ($team === null) {
            $this->teamTranslationName = '';

            return;
        }

        $locale = LocaleSupport::normalize($this->teamPreviewLocale);
        if ($locale === $team->normalizedOriginalLanguage()) {
            $locale = $this->defaultTranslationLocaleForTeam($team);
            $this->teamPreviewLocale = $locale;
        }

        $translation = $team->translations
            ->first(fn ($row) => $row->locale === $locale);

        $this->teamTranslationName = (string) ($translation?->name ?? '');
    }

    private function defaultTranslationLocaleForTeam(InternalTeam $team): string
    {
        $targets = LocaleSupport::targetLocalesForSource($team->normalizedOriginalLanguage());
        $preferred = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

        if (in_array($preferred, $targets, true)) {
            return $preferred;
        }

        return $targets[0] ?? $preferred;
    }

    // --- Workers (admin of medewerker) ------------------------------------

    public function openAddWorker(int $teamId): void
    {
        $team = InternalTeam::findOrFail($teamId);
        Gate::authorize('update', $team);

        $this->expandTeam($teamId);
        $this->addingWorkerTeamId = $teamId;

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        if (! $tenant->canAddSeat()) {
            $this->addError('workerFirstName', __('team.errors.seat_limit'));
            return;
        }

        $this->editingWorkerId = null;
        $this->resetWorkerPhotoState();
        $this->reset(['workerFirstName', 'workerLastName', 'workerEmail', 'workerPhone', 'workerIsExternal', 'workerCompanyName', 'workerSsin', 'selectedWorkerLocationIds']);
        $this->resetErrorBag(['workerFirstName', 'workerLastName', 'workerEmail', 'workerPhone', 'workerIsExternal', 'workerCompanyName', 'workerSsin', 'selectedWorkerLocationIds', 'workerPhoto']);
        $this->showWorkerModal = true;
    }

    public function updatedWorkerIsExternal(bool $value): void
    {
        if (! $value) {
            $this->workerCompanyName = '';
            $this->resetErrorBag(['workerCompanyName']);
        }
    }

    public function saveWorker(
        CreateWorkerAction $createWorker,
        UpdateWorkerAction $updateWorker,
        UpdateWorkerPhotoAction $updateWorkerPhoto,
        DeleteWorkerPhotoAction $deleteWorkerPhoto,
    ): void {
        $presenceComplianceEnabled = $this->tenantPresenceComplianceEnabled();

        if ($this->editingWorkerId) {
            // Edit mode
            $worker = $this->authorizedWorker((int) $this->editingWorkerId);

            $request = new UpdateWorkerRequest;
            $validated = $this->validate(
                $this->workerModalValidationRules($request->rules(), $presenceComplianceEnabled),
                $this->workerModalValidationMessages($presenceComplianceEnabled),
            );

            $payload = [
                'first_name' => $validated['workerFirstName'],
                'last_name' => $validated['workerLastName'],
                'email' => $validated['workerEmail'] ?? null,
                'phone' => $validated['workerPhone'] ?? null,
                'is_external' => (bool) ($validated['workerIsExternal'] ?? false),
                'company_name' => $validated['workerCompanyName'] ?? null,
                'location_ids' => $this->selectedWorkerLocationIds,
            ];
            if ($presenceComplianceEnabled) {
                $payload['ssin'] = preg_replace('/\D+/', '', (string) ($validated['workerSsin'] ?? '')) ?: null;
            }

            $worker = $updateWorker->handle($worker, $payload, (int) auth()->id());

            $this->persistWorkerPhoto($worker, $updateWorkerPhoto, $deleteWorkerPhoto);
        } else {
            // Create mode
            $team = InternalTeam::findOrFail((int) $this->addingWorkerTeamId);
            Gate::authorize('update', $team);

            $request = new StoreWorkerRequest;
            $validated = $this->validate(
                $this->workerModalValidationRules($request->rules(), $presenceComplianceEnabled),
                $this->workerModalValidationMessages($presenceComplianceEnabled),
            );

            try {
                $payload = [
                    'first_name' => $validated['workerFirstName'],
                    'last_name' => $validated['workerLastName'],
                    'email' => $validated['workerEmail'] ?? null,
                    'phone' => $validated['workerPhone'] ?? null,
                    'is_external' => (bool) ($validated['workerIsExternal'] ?? false),
                    'company_name' => $validated['workerCompanyName'] ?? null,
                    'location_ids' => $this->selectedWorkerLocationIds,
                ];
                if ($presenceComplianceEnabled) {
                    $payload['ssin'] = preg_replace('/\D+/', '', (string) ($validated['workerSsin'] ?? '')) ?: null;
                }

                $worker = $createWorker->handle($team, $payload, (int) auth()->id());
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() === 'seat_limit_exceeded') {
                    $this->addError('workerFirstName', __('team.errors.seat_limit'));

                    return;
                }

                throw $e;
            }

            $this->persistWorkerPhoto($worker, $updateWorkerPhoto, $deleteWorkerPhoto);
        }

        $this->cancelWorkerModal();
        $this->dispatch('saved');
    }

    /**
     * @param  array<string, mixed>  $requestRules
     * @return array<string, mixed>
     */
    private function workerModalValidationRules(array $requestRules, bool $presenceComplianceEnabled): array
    {
        $rules = [
            'workerFirstName' => $requestRules['first_name'],
            'workerLastName' => $requestRules['last_name'],
            'workerEmail' => $requestRules['email'],
            'workerPhone' => $requestRules['phone'],
            'workerIsExternal' => $requestRules['is_external'],
            'workerCompanyName' => $requestRules['company_name'],
            'workerPhoto' => $requestRules['photo'],
        ];

        if ($presenceComplianceEnabled) {
            $rules['workerSsin'] = $requestRules['ssin'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function workerModalValidationMessages(bool $presenceComplianceEnabled): array
    {
        $messages = [
            'workerFirstName.required' => __('team.errors.worker_name_required'),
            'workerLastName.required' => __('team.errors.worker_name_required'),
            'workerEmail.email' => __('team.errors.worker_email_invalid'),
            'workerEmail.max' => __('team.errors.worker_email_max'),
            'workerPhone.max' => __('team.errors.worker_phone_max'),
            'workerCompanyName.max' => __('team.errors.worker_company_name_max'),
            'workerPhoto.image' => __('team.errors.worker_photo_invalid'),
            'workerPhoto.mimes' => __('team.errors.worker_photo_invalid'),
            'workerPhoto.max' => __('team.errors.worker_photo_max'),
        ];

        if ($presenceComplianceEnabled) {
            $messages['workerSsin.regex'] = __('team.errors.worker_ssin_invalid');
        }

        return $messages;
    }

    private function tenantPresenceComplianceEnabled(): bool
    {
        $tenantId = Tenancy::id();
        if ($tenantId === null) {
            return false;
        }

        $tenant = Tenant::query()->find($tenantId);

        return $tenant instanceof Tenant && $tenant->presenceComplianceEnabled();
    }

    public function clearWorkerPhotoSelection(): void
    {
        $this->workerPhoto = null;
        $this->removeWorkerPhoto = true;
        $this->existingWorkerPhotoUrl = null;
        $this->resetErrorBag(['workerPhoto']);
    }

    public function workerPhotoPreviewUrl(): ?string
    {
        if ($this->workerPhoto instanceof TemporaryUploadedFile) {
            try {
                return $this->workerPhoto->temporaryUrl();
            } catch (\Throwable) {
                // Fall through to existing stored photo.
            }
        }

        return $this->existingWorkerPhotoUrl;
    }

    public function openEditWorker(int $workerId): void
    {
        $worker = $this->authorizedWorker($workerId);
        $this->editingWorkerId = $workerId;
        $this->workerFirstName = $worker->first_name;
        $this->workerLastName = $worker->last_name;
        $this->workerEmail = $worker->email ?? '';
        $this->workerPhone = $worker->phone ?? '';
        $this->workerIsExternal = (bool) $worker->is_external;
        $this->workerCompanyName = $worker->company_name ?? '';
        $this->workerSsin = $worker->ssin ?? '';
        $this->selectedWorkerLocationIds = $worker->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $this->resetWorkerPhotoState();
        $this->existingWorkerPhotoUrl = $worker->photoPublicUrl();
        $this->showWorkerModal = true;
    }

    public function cancelWorkerModal(): void
    {
        $this->reset([
            'showWorkerModal',
            'editingWorkerId',
            'addingWorkerTeamId',
            'workerFirstName',
            'workerLastName',
            'workerEmail',
            'workerPhone',
            'workerIsExternal',
            'workerCompanyName',
            'workerSsin',
            'selectedWorkerLocationIds',
            'workerPhoto',
            'removeWorkerPhoto',
            'existingWorkerPhotoUrl',
        ]);
        $this->resetErrorBag(['workerFirstName', 'workerLastName', 'workerEmail', 'workerPhone', 'workerIsExternal', 'workerCompanyName', 'workerSsin', 'selectedWorkerLocationIds', 'workerPhoto']);
    }

    private function resetWorkerPhotoState(): void
    {
        $this->workerPhoto = null;
        $this->removeWorkerPhoto = false;
        $this->existingWorkerPhotoUrl = null;
    }

    private function persistWorkerPhoto(
        Worker $worker,
        UpdateWorkerPhotoAction $updateWorkerPhoto,
        DeleteWorkerPhotoAction $deleteWorkerPhoto,
    ): void {
        if ($this->workerPhoto instanceof TemporaryUploadedFile) {
            $updateWorkerPhoto->handle($worker, $this->workerPhoto, (int) auth()->id());

            return;
        }

        if ($this->removeWorkerPhoto) {
            $deleteWorkerPhoto->handle($worker, (int) auth()->id());
        }
    }

    public function resetWorkerIcon(int $workerId, ResetWorkerIconAction $resetIcon): void
    {
        $worker = $this->authorizedWorker($workerId);
        $resetIcon->handle($worker, (int) auth()->id());
    }

    public function setWorkerActive(int $workerId, bool $active, SetWorkerActiveAction $setActive): void
    {
        $worker = $this->authorizedWorker($workerId);

        try {
            $setActive->handle($worker, $active, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'seat_limit_exceeded') {
                $this->addError('workerFirstName', __('team.errors.seat_limit'));
            }
        }
    }

    public function setWorkerTeamleader(int $workerId, bool $isTeamleader, SetWorkerTeamleaderAction $setTeamleader): void
    {
        $worker = $this->authorizedWorker($workerId);
        $setTeamleader->handle($worker, $isTeamleader, (int) auth()->id());
    }

    public function deleteWorker(int $workerId, DeleteWorkerAction $deleteWorker): void
    {
        $worker = $this->authorizedWorker($workerId);
        $deleteWorker->handle($worker, (int) auth()->id());
    }

    private function authorizedWorker(int $workerId): Worker
    {
        $worker = Worker::findOrFail($workerId);
        Gate::authorize('update', $worker->team);

        return $worker;
    }

    private function expandTeam(int $teamId): void
    {
        if (! in_array($teamId, $this->expandedTeamIds, true)) {
            $this->expandedTeamIds[] = $teamId;
        }
    }

    // --- Worker CSV / Excel import ----------------------------------------

    public function downloadSampleCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('create', InternalTeam::class);

        $headers = ['team_name', 'first_name', 'last_name', 'email', 'phone', 'company_name', 'location_names'];

        $sampleRow = [
            __('team.workers.import_sample.team_name'),
            __('team.workers.import_sample.first_name'),
            __('team.workers.import_sample.last_name'),
            __('team.workers.import_sample.email'),
            __('team.workers.import_sample.phone'),
            __('team.workers.import_sample.company_name'),
            __('team.workers.import_sample.location_names'),
        ];

        return response()->streamDownload(function () use ($headers, $sampleRow) {
            echo "\xEF\xBB\xBF";
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fputcsv($file, $sampleRow);
            fclose($file);
        }, 'winprox_workers_sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadSampleXlsx(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('create', InternalTeam::class);

        $rows = [
            ['team_name', 'first_name', 'last_name', 'email', 'phone', 'company_name', 'location_names'],
            [
                __('team.workers.import_sample.team_name'),
                __('team.workers.import_sample.first_name'),
                __('team.workers.import_sample.last_name'),
                __('team.workers.import_sample.email'),
                __('team.workers.import_sample.phone'),
                __('team.workers.import_sample.company_name'),
                __('team.workers.import_sample.location_names'),
            ],
        ];

        return response()->streamDownload(function () use ($rows) {
            $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'winprox-workers-sample-'.uniqid('', true).'.xlsx';
            try {
                \App\Support\Import\MinimalXlsxWriter::write($tempPath, $rows);
                readfile($tempPath);
            } finally {
                @unlink($tempPath);
            }
        }, 'winprox_workers_sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function openWorkerImportModal(): void
    {
        $this->authorize('create', InternalTeam::class);
        $this->workerImportFile = null;
        $this->workerImportErrors = [];
        $this->workerImportedCount = null;
        $this->workersImportNotice = null;
        $this->showWorkerImportModal = true;
    }

    public function openClockPointQr(EnsureDefaultClockPointAction $ensure): void
    {
        $this->authorize('create', InternalTeam::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $clockPoint = $ensure->handle($tenant, __('team.clock_point_qr.default_name'), auth()->id());
        $this->authorize('view', $clockPoint);

        $this->redirect(route('time.clock-points.qr', $clockPoint), navigate: false);
    }

    public function closeWorkerImportModal(): void
    {
        $this->showWorkerImportModal = false;
        $this->workerImportFile = null;
        $this->workerImportErrors = [];
        $this->workerImportedCount = null;
    }

    public function importWorkers(ImportWorkersAction $importWorkers): void
    {
        $this->authorize('create', InternalTeam::class);

        if ($this->workerImportFile === null || ! ($this->workerImportFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)) {
            $this->workerImportErrors = [__('team.errors.import_file_required')];

            return;
        }

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['file' => $this->workerImportFile],
            ImportWorkersRequest::getReusableRules(),
            ImportWorkersRequest::getReusableMessages()
        );

        if ($validator->fails()) {
            $this->workerImportErrors = $validator->errors()->all();

            return;
        }

        $dto = new ImportWorkersData(
            filePath: $this->workerImportFile->getRealPath(),
            originalName: $this->workerImportFile->getClientOriginalName(),
        );

        $result = $importWorkers->handle($dto, Tenancy::id(), (int) auth()->id());

        if ($result['success']) {
            $this->workerImportedCount = $result['count'];
            $this->workerImportErrors = [];
            $this->workersImportNotice = __('team.workers.flash.imported', ['count' => $result['count']]);
            $this->workersImportNoticeType = 'success';
            $this->closeWorkerImportModal();
        } else {
            $this->workerImportErrors = $result['errors'];
        }
    }

    public function deleteWorkerImportBatch(string $batchId, DeleteWorkerImportBatchAction $deleteBatch): void
    {
        $this->authorize('create', InternalTeam::class);

        $tenantId = Tenancy::id();
        $summary = WorkerImportBatchRegistry::summary($tenantId, $batchId);

        if (! $summary['can_delete']) {
            $this->workersImportNotice = __('team.import_history.nothing_deletable');
            $this->workersImportNoticeType = 'error';

            return;
        }

        $dto = new DeleteWorkerImportBatchData(importBatchId: $batchId);
        $result = $deleteBatch->handle($dto, $tenantId, (int) auth()->id());

        if ($result['success']) {
            $teamsDeleted = (int) ($result['deleted_team_count'] ?? 0);

            if ($result['preserved_count'] > 0) {
                $this->workersImportNotice = $teamsDeleted > 0
                    ? __('team.import_history.partially_deleted_with_teams', [
                        'deleted'   => $result['deleted_count'],
                        'preserved' => $result['preserved_count'],
                        'teams'     => $teamsDeleted,
                    ])
                    : __('team.import_history.partially_deleted', [
                        'deleted'   => $result['deleted_count'],
                        'preserved' => $result['preserved_count'],
                    ]);
            } else {
                $this->workersImportNotice = $teamsDeleted > 0
                    ? __('team.import_history.fully_deleted_with_teams', [
                        'count' => $result['deleted_count'],
                        'teams' => $teamsDeleted,
                    ])
                    : __('team.import_history.fully_deleted', [
                        'count' => $result['deleted_count'],
                    ]);
            }

            $this->workersImportNoticeType = 'success';
        } else {
            $this->workersImportNotice = $result['errors'][0] ?? __('team.import_history.delete_failed');
            $this->workersImportNoticeType = 'error';
        }
    }

    public function openCreateCheckList(): void
    {
        $this->authorize('create', UnitCheckList::class);
        $this->editingCheckListId = null;
        $this->checkListName = '';
        $this->checkListItemsText = '';
        $this->checkListIsActive = true;
        $this->checkListTeamId = null;
        $this->checkListPreviewLocale = '';
        $this->checkListTranslationName = '';
        $this->checkListTranslationItemsText = '';
        $this->showCheckListsSection = true;
        $this->showCheckListModal = true;
        $this->resetErrorBag();
    }

    public function openEditCheckList(int $listId): void
    {
        $list = UnitCheckList::query()->with(['items', 'translations'])->findOrFail($listId);
        $this->authorize('update', $list);
        $this->editingCheckListId = (int) $list->id;
        $this->checkListName = $list->name;
        $this->checkListItemsText = $list->items->pluck('label')->implode("\n");
        $this->checkListIsActive = (bool) $list->is_active;
        $this->checkListTeamId = $list->internal_team_id;
        $this->checkListPreviewLocale = $this->defaultTranslationLocaleForCheckList($list);
        $this->hydrateCheckListTranslationInput($list);
        $this->showCheckListModal = true;
        $this->resetErrorBag();
    }

    public function updatedCheckListPreviewLocale(): void
    {
        if ($this->editingCheckListId === null) {
            $this->checkListTranslationName = '';
            $this->checkListTranslationItemsText = '';

            return;
        }

        $list = UnitCheckList::query()
            ->with(['items', 'translations'])
            ->find($this->editingCheckListId);

        $this->hydrateCheckListTranslationInput($list);
    }

    public function saveCheckListTranslationOverride(ImportUnitCheckListTranslationsAction $importTranslations): void
    {
        if ($this->editingCheckListId === null) {
            return;
        }

        $list = UnitCheckList::query()
            ->with(['items', 'translations'])
            ->findOrFail($this->editingCheckListId);
        $this->authorize('update', $list);

        if (! $list->is_active) {
            $this->addError('checkListTranslationName', __('unit_checks.lists.errors.translation_requires_active'));

            return;
        }

        $validated = $this->validate([
            'checkListPreviewLocale' => ['required', 'string', 'max:5'],
            'checkListTranslationName' => ['required', 'string', 'max:255'],
            'checkListTranslationItemsText' => ['required', 'string'],
        ], [
            'checkListTranslationName.required' => __('unit_checks.lists.errors.name_required'),
            'checkListTranslationItemsText.required' => __('unit_checks.lists.errors.items_required'),
        ]);

        $locale = LocaleSupport::normalize((string) $validated['checkListPreviewLocale']);
        if ($locale === $list->normalizedOriginalLanguage()) {
            $this->addError('checkListTranslationName', __('issues.errors.translation_same_as_source'));

            return;
        }

        $name = trim((string) $validated['checkListTranslationName']);
        $rawItems = preg_split("/\r\n|\n|\r/", (string) $validated['checkListTranslationItemsText']) ?: [];
        $items = [];
        foreach ($rawItems as $item) {
            $label = trim((string) $item);
            if ($label !== '') {
                $items[] = $label;
            }
        }

        if ($name === '' || $items === []) {
            $this->addError('checkListTranslationName', __('issues.errors.translation_import_invalid'));

            return;
        }

        try {
            $importTranslations->handle([
                [
                    'unit_check_list_id' => $list->id,
                    'locale' => $locale,
                    'name' => $name,
                    'items' => $items,
                ],
            ], (int) auth()->id());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('checkListTranslationName', (string) $message);
                }
            }

            return;
        }

        $this->hydrateCheckListTranslationInput($list->fresh(['items', 'translations']));
        session()->flash('success', __('unit_checks.lists.flash.translation_saved'));
    }

    public function closeCheckListModal(): void
    {
        $this->showCheckListModal = false;
        $this->editingCheckListId = null;
        $this->checkListName = '';
        $this->checkListItemsText = '';
        $this->checkListIsActive = true;
        $this->checkListTeamId = null;
        $this->checkListPreviewLocale = '';
        $this->checkListTranslationName = '';
        $this->checkListTranslationItemsText = '';
        $this->resetErrorBag();
    }

    private function hydrateCheckListTranslationInput(?UnitCheckList $list): void
    {
        if ($list === null) {
            $this->checkListTranslationName = '';
            $this->checkListTranslationItemsText = '';

            return;
        }

        $locale = LocaleSupport::normalize($this->checkListPreviewLocale);
        if ($locale === '' || $locale === $list->normalizedOriginalLanguage()) {
            $locale = $this->defaultTranslationLocaleForCheckList($list);
            $this->checkListPreviewLocale = $locale;
        }

        $translation = $list->translations
            ->first(fn ($row) => $row->locale === $locale);

        $this->checkListTranslationName = (string) ($translation?->name ?? '');
        $translatedItems = is_array($translation?->items) ? $translation->items : [];
        $this->checkListTranslationItemsText = collect($translatedItems)
            ->map(static fn ($item) => trim((string) $item))
            ->filter(static fn (string $item) => $item !== '')
            ->implode("\n");
    }

    private function defaultTranslationLocaleForCheckList(UnitCheckList $list): string
    {
        $targets = LocaleSupport::targetLocalesForSource($list->normalizedOriginalLanguage());
        $preferred = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

        if (in_array($preferred, $targets, true)) {
            return $preferred;
        }

        return $targets[0] ?? $preferred;
    }

    public function saveCheckList(SaveUnitCheckListAction $saveList): void
    {
        $tenantId = (int) Tenancy::id();
        $payload = [
            'name' => trim($this->checkListName),
            'items' => $this->checkListItemsText,
            'is_active' => $this->checkListIsActive,
            'internal_team_id' => $this->checkListTeamId,
        ];

        // Nieuwe lijst: brontaal = huidige app-taal. Bij bewerken blijft de brontaal ongewijzigd.
        if ($this->editingCheckListId === null) {
            $payload['original_language'] = LocaleSupport::normalize(app()->getLocale());
        }

        $validator = Validator::make(
            $payload,
            SaveUnitCheckListRequest::staticRules($tenantId),
            SaveUnitCheckListRequest::validationMessages(),
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $map = [
                        'items' => 'checkListItemsText',
                        'name' => 'checkListName',
                        'internal_team_id' => 'checkListTeamId',
                    ];
                    $this->addError($map[$field] ?? $field, $message);
                }
            }

            return;
        }

        try {
            if ($this->editingCheckListId === null) {
                $this->authorize('create', UnitCheckList::class);
                $saveList->handle(
                    SaveUnitCheckListData::fromValidated($validator->validated()),
                    $tenantId,
                    null,
                    (int) auth()->id(),
                );
            } else {
                $list = UnitCheckList::query()->findOrFail($this->editingCheckListId);
                $this->authorize('update', $list);
                $saveList->handle(
                    SaveUnitCheckListData::fromValidated($validator->validated()),
                    $tenantId,
                    $list,
                    (int) auth()->id(),
                );
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $map = [
                        'items' => 'checkListItemsText',
                        'name' => 'checkListName',
                        'internal_team_id' => 'checkListTeamId',
                    ];
                    $this->addError($map[$field] ?? $field, $message);
                }
            }

            return;
        }

        $this->closeCheckListModal();
    }

    public function copyCheckListFromStarter(string $starterKey, CopyUnitCheckListFromStarterAction $copy): void
    {
        $this->authorize('create', UnitCheckList::class);
        $this->showCheckListsSection = true;

        try {
            $copy->handle(
                $starterKey,
                (int) Tenancy::id(),
                null,
                (int) auth()->id(),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('checkListName', (string) $message);
                }
            }
        }
    }

    public function deactivateCheckList(int $listId, DeactivateUnitCheckListAction $deactivate): void
    {
        $list = UnitCheckList::query()->findOrFail($listId);
        $this->authorize('delete', $list);
        $deactivate->handle($list, (int) auth()->id());
    }

    public function deleteCheckList(int $listId, DeleteUnitCheckListAction $delete): void
    {
        $list = UnitCheckList::query()->findOrFail($listId);
        $this->authorize('delete', $list);

        try {
            $delete->handle($list, (int) auth()->id());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('checkListName', (string) $message);
                }
            }

            return;
        }

        if ($this->editingCheckListId === $listId) {
            $this->closeCheckListModal();
        }
    }

    public function render()
    {
        $user = auth()->user();
        $canManageUsers = $user->can('create', User::class);

        $isBackoffice = $this->isBackofficeSection();

        $colleagues = ($isBackoffice && $canManageUsers)
            ? User::where('tenant_id', Tenancy::id())
                ->where('is_superuser', false)
                ->when(trim($this->search) !== '', function ($query) {
                    $term = '%'.trim($this->search).'%';
                    $query->where(function ($userQuery) use ($term) {
                        $userQuery->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
                })
                ->orderBy('name')
                ->get()
            : collect();

        $teams = $isBackoffice
            ? collect()
            : InternalTeam::with([
                'translations',
                'workers' => fn ($q) => $q->orderBy('first_name')->orderBy('last_name'),
            ])
                ->when(trim($this->search) !== '', function ($query) {
                    $term = '%'.trim($this->search).'%';
                    $query->where(function ($teamQuery) use ($term) {
                        $teamQuery->where('name', 'like', $term)
                            ->orWhereHas('workers', function ($workerQuery) use ($term) {
                                $workerQuery->where('first_name', 'like', $term)
                                    ->orWhere('last_name', 'like', $term);
                            });
                    });
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

        if (! $isBackoffice && trim($this->search) !== '') {
            foreach ($teams as $team) {
                $this->expandTeam((int) $team->id);
            }
        }

        $categories = $isBackoffice
            ? collect()
            : \App\Models\Category::where('tenant_id', Tenancy::id())
                ->with('translations')
                ->orderBy('name')
                ->get(['id', 'name', 'original_language']);

        $teamTranslationLocales = config('locales.labels', []);
        if ($this->showTeamModal && $this->editingTeamId !== null) {
            $editingTeam = InternalTeam::query()->find($this->editingTeamId);

            if ($editingTeam !== null) {
                $sourceLocale = $editingTeam->normalizedOriginalLanguage();
                $teamTranslationLocales = array_filter(
                    $teamTranslationLocales,
                    fn (string $label, string $code): bool => $code !== $sourceLocale,
                    ARRAY_FILTER_USE_BOTH,
                );
            }
        }

        $checkListTranslationLocales = config('locales.labels', []);
        if ($this->showCheckListModal && $this->editingCheckListId !== null) {
            $editingCheckList = UnitCheckList::query()->find($this->editingCheckListId);

            if ($editingCheckList !== null) {
                $sourceLocale = $editingCheckList->normalizedOriginalLanguage();
                $checkListTranslationLocales = array_filter(
                    $checkListTranslationLocales,
                    fn (string $label, string $code): bool => $code !== $sourceLocale,
                    ARRAY_FILTER_USE_BOTH,
                );
            }
        }

        $tenantId = Tenancy::id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        $workerImportBatches = $isBackoffice
            ? collect()
            : WorkerImportBatchRegistry::recentBatchesForTenant($tenantId)
                ->map(fn (array $batch) => array_merge(
                    $batch,
                    WorkerImportBatchRegistry::summary($tenantId, $batch['batch_id']),
                ));

        return view('livewire.pages.team', [
            'colleagues' => $colleagues,
            'teams' => $teams,
            'workerImportBatches' => $workerImportBatches,
            'canManageUsers' => $canManageUsers,
            'canManageTeams' => $user->can('create', InternalTeam::class),
            'canEditContent' => $user->can('manageContent', InternalTeam::class),
            'hasTimeModule' => $tenant?->hasTimeModule() ?? false,
            'presenceComplianceEnabled' => $tenant instanceof Tenant && $tenant->presenceComplianceEnabled(),
            'canImportWorkers' => $tenant?->hasCsvWorkersImport() ?? false,
            'roles' => User::ROLES,
            'categories' => $isBackoffice ? collect() : $categories,
            'teamTranslationLocales' => $teamTranslationLocales,
            'checkListTranslationLocales' => $checkListTranslationLocales,
            'checkLists' => $isBackoffice
                ? collect()
                : UnitCheckList::query()
                    ->with(['internalTeam.translations', 'translations'])
                    ->withCount(['items', 'units'])
                    ->orderBy('name')
                    ->get(),
            'checkListTeams' => $isBackoffice
                ? collect()
                : InternalTeam::query()
                    ->where('is_active', true)
                    ->with('translations')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'original_language']),
            'checkListStarters' => $isBackoffice ? [] : config('unit_check_starters', []),
            'allLocations' => Location::query()->orderBy('name')->get(['id', 'name', 'address']),
            'punchClockTeams' => InternalTeam::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}

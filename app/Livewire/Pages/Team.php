<?php

namespace App\Livewire\Pages;

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
use App\Actions\Team\ResetWorkerIconAction;
use App\Actions\Team\SetColleagueActiveAction;
use App\Actions\Team\SetTeamActiveAction;
use App\Actions\Team\SetWorkerActiveAction;
use App\Actions\Team\SetWorkerTeamleaderAction;
use App\Actions\Team\SyncTeamCategoriesAction;
use App\Actions\Team\UpdateColleagueAction;
use App\Actions\Team\UpdateTeamAction;
use App\Http\Requests\Team\StoreColleagueRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\StoreWorkerRequest;
use App\Http\Requests\Team\UpdateColleagueRequest;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Gebruikers-hub: collega-gebruikers (admin), operationele teams (+ team-QR) en workers.
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

    // Team (modal)
    public bool $showTeamModal = false;
    public ?int $editingTeamId = null;
    public string $teamName = '';
    public int $teamSortOrder = 0;
    public bool $teamIsActive = true;
    public string $teamSessionLifespanType = 'daily';
    public ?int $teamSessionLifespanCustomHours = null;

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

    public string $workerFirstName = '';
    public string $workerLastName = '';
    public string $workerEmail = '';
    public string $workerPhone = '';

    // Worker CSV import
    public bool $showWorkerImportModal = false;
    public $workerImportFile = null;
    public array $workerImportErrors = [];
    public ?int $workerImportedCount = null;

    public function mount(): void
    {
        if ($this->highlightWorkerId !== null) {
            $worker = Worker::query()
                ->where('tenant_id', Tenancy::id())
                ->find($this->highlightWorkerId);

            if ($worker !== null) {
                $this->expandTeam((int) $worker->internal_team_id);
            }
        }

        if ($this->highlightTeamId !== null) {
            $this->expandTeam($this->highlightTeamId);
        }
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
        if (! $tenant->canAddUser()) {
            $this->addError('colleagueCreate', __('team.errors.user_limit'));
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
                if ($e->getMessage() === 'user_limit_exceeded') {
                    $this->addError('colleagueEmail', __('team.errors.user_limit'));

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
            ],
            $this->colleagueValidationMessages($messages),
        );

        $payload = [
            'name' => $validated['colleagueName'],
            'email' => $validated['colleagueEmail'],
            'locale' => $validated['colleagueLocale'],
            'role' => $validated['colleagueRole'],
        ];

        if ($validated['colleaguePassword'] !== '') {
            $payload['password'] = $validated['colleaguePassword'];
        }

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
        $setActive->handle($user, $active, (int) auth()->id());
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
            'editingColleagueId',
        ]);
        $this->colleagueLocale = (string) (auth()->user()?->locale ?: config('locales.default', 'nl'));
        $this->colleagueRole = User::ROLE_EMPLOYEE;
        $this->colleagueSendAccountEmail = true;
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
        $team = InternalTeam::findOrFail($id);
        Gate::authorize('update', $team);

        $this->editingTeamId = $team->id;
        $this->teamName = $team->name;
        $this->teamSortOrder = $team->sort_order;
        $this->teamIsActive = $team->is_active;

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

        $this->resetErrorBag();
        $this->showTeamModal = true;
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
                'session_lifespan_hours' => $sessionLifespanHours,
            ], (int) auth()->id());

            Gate::authorize('syncCategories', $team);
            $syncCategories->handle($team, $this->selectedCategoryIds, (int) auth()->id());
        } else {
            Gate::authorize('create', InternalTeam::class);

            $team = $createTeam->handle([
                'name' => $validated['teamName'],
                'sort_order' => $this->teamSortOrder,
                'is_active' => $this->teamIsActive,
                'session_lifespan_hours' => $sessionLifespanHours,
            ], (int) Tenancy::id(), (int) auth()->id());

            Gate::authorize('syncCategories', $team);
            $syncCategories->handle($team, $this->selectedCategoryIds, (int) auth()->id());
        }

        $this->showTeamModal = false;
        $this->resetTeamForm();
        $this->dispatch('saved');
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
        $this->reset(['teamName', 'teamSortOrder', 'teamIsActive', 'teamSessionLifespanType', 'teamSessionLifespanCustomHours', 'editingTeamId', 'selectedCategoryIds']);
        $this->teamIsActive = true;
        $this->teamSessionLifespanType = 'daily';
        $this->teamSessionLifespanCustomHours = null;
        $this->selectedCategoryIds = [];
        $this->resetErrorBag();
    }

    // --- Workers (admin of medewerker) ------------------------------------

    public function openAddWorker(int $teamId): void
    {
        $team = InternalTeam::findOrFail($teamId);
        Gate::authorize('update', $team);

        $this->expandTeam($teamId);
        $this->reset(['workerFirstName', 'workerLastName', 'workerEmail', 'workerPhone']);
        $this->resetErrorBag(['workerFirstName', 'workerLastName', 'workerEmail', 'workerPhone']);
        $this->addingWorkerTeamId = $teamId;
    }

    public function saveWorker(CreateWorkerAction $createWorker): void
    {
        $team = InternalTeam::findOrFail((int) $this->addingWorkerTeamId);
        Gate::authorize('update', $team);

        $request = new StoreWorkerRequest;
        $validated = $this->validate(
            [
                'workerFirstName' => $request->rules()['first_name'],
                'workerLastName' => $request->rules()['last_name'],
                'workerEmail' => $request->rules()['email'],
                'workerPhone' => $request->rules()['phone'],
            ],
            [
                'workerFirstName.required' => __('team.errors.worker_name_required'),
                'workerLastName.required' => __('team.errors.worker_name_required'),
                'workerEmail.email' => __('team.errors.worker_email_invalid'),
                'workerEmail.max' => __('team.errors.worker_email_max'),
                'workerPhone.max' => __('team.errors.worker_phone_max'),
            ],
        );

        $createWorker->handle($team, [
            'first_name' => $validated['workerFirstName'],
            'last_name' => $validated['workerLastName'],
            'email' => $validated['workerEmail'] ?? null,
            'phone' => $validated['workerPhone'] ?? null,
        ], (int) auth()->id());

        $this->reset(['workerFirstName', 'workerLastName', 'workerEmail', 'workerPhone', 'addingWorkerTeamId']);
        $this->dispatch('saved');
    }

    public function cancelWorker(): void
    {
        $this->reset(['workerFirstName', 'workerLastName', 'workerEmail', 'workerPhone', 'addingWorkerTeamId']);
        $this->resetErrorBag(['workerFirstName', 'workerLastName', 'workerEmail', 'workerPhone']);
    }

    public function resetWorkerIcon(int $workerId, ResetWorkerIconAction $resetIcon): void
    {
        $worker = $this->authorizedWorker($workerId);
        $resetIcon->handle($worker, (int) auth()->id());
    }

    public function setWorkerActive(int $workerId, bool $active, SetWorkerActiveAction $setActive): void
    {
        $worker = $this->authorizedWorker($workerId);
        $setActive->handle($worker, $active, (int) auth()->id());
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

    // --- Worker CSV import ------------------------------------------------

    public function downloadSampleCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('create', InternalTeam::class);

        $headers = ['team_name', 'first_name', 'last_name', 'email', 'phone', 'external_id'];

        $sampleRow = [
            __('team.workers.import_sample.team_name'),
            __('team.workers.import_sample.first_name'),
            __('team.workers.import_sample.last_name'),
            __('team.workers.import_sample.email'),
            __('team.workers.import_sample.phone'),
            __('team.workers.import_sample.external_id'),
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

    public function openWorkerImportModal(): void
    {
        $this->authorize('create', InternalTeam::class);
        $this->workerImportFile = null;
        $this->workerImportErrors = [];
        $this->workerImportedCount = null;
        $this->showWorkerImportModal = true;
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
            session()->flash('success', __('team.workers.flash.imported', ['count' => $result['count']]));
            $this->closeWorkerImportModal();
        } else {
            $this->workerImportErrors = $result['errors'];
        }
    }

    public function deleteWorkerImportBatch(string $batchId, DeleteWorkerImportBatchAction $deleteBatch): void
    {
        $this->authorize('create', InternalTeam::class);

        $dto = new DeleteWorkerImportBatchData(importBatchId: $batchId);
        $deleteBatch->handle($dto, Tenancy::id(), (int) auth()->id());
    }

    public function render()
    {
        $user = auth()->user();
        $canManageUsers = $user->can('create', User::class);

        $colleagues = $canManageUsers
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

        $teams = InternalTeam::with(['workers' => fn ($q) => $q->orderBy('first_name')->orderBy('last_name')])
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

        if (trim($this->search) !== '') {
            foreach ($teams as $team) {
                $this->expandTeam((int) $team->id);
            }
        }

        $categories = \App\Models\Category::where('tenant_id', Tenancy::id())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.pages.team', [
            'colleagues' => $colleagues,
            'teams' => $teams,
            'canManageUsers' => $canManageUsers,
            'canManageTeams' => $user->can('create', InternalTeam::class),
            'canEditContent' => $user->can('manageContent', InternalTeam::class),
            'roles' => User::ROLES,
            'categories' => $categories,
        ]);
    }
}

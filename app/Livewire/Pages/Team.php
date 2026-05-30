<?php

namespace App\Livewire\Pages;

use App\Actions\Team\CreateColleagueAction;
use App\Actions\Team\CreateTeamAction;
use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\DeleteWorkerAction;
use App\Actions\Team\ResetWorkerIconAction;
use App\Actions\Team\SetColleagueActiveAction;
use App\Actions\Team\SetTeamActiveAction;
use App\Actions\Team\SetWorkerActiveAction;
use App\Actions\Team\SetWorkerTeamleaderAction;
use App\Actions\Team\UpdateColleagueAction;
use App\Actions\Team\UpdateOrganisationAction;
use App\Actions\Team\UpdateTeamAction;
use App\Http\Requests\Team\StoreColleagueRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\StoreWorkerRequest;
use App\Http\Requests\Team\UpdateColleagueRequest;
use App\Http\Requests\Team\UpdateOrganisationRequest;
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
use App\Support\TenantLogoStorage;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Team-hub (V2-spec §6): collega-gebruikers + organisatie (alleen admin),
 * operationele teams (+ team-QR) en workers (icoon-reset/lockout/actief/teamleader).
 * Dun: validatie via Form Requests, mutaties via Actions; RBAC via role + policy.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Team extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    // Organisatie
    public string $orgName = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $orgLogo = null;

    // Collega-gebruiker (modal)
    public bool $showColleagueModal = false;
    public ?int $editingColleagueId = null;
    public string $colleagueName = '';
    public string $colleagueEmail = '';
    public string $colleagueRole = User::ROLE_EMPLOYEE;

    // Team (modal)
    public bool $showTeamModal = false;
    public ?int $editingTeamId = null;
    public string $teamName = '';
    public int $teamSortOrder = 0;
    public bool $teamIsActive = true;

    // Worker toevoegen (inline per team)
    public ?int $addingWorkerTeamId = null;
    public string $workerFirstName = '';
    public string $workerLastName = '';

    public function mount(): void
    {
        $this->orgName = (string) (auth()->user()->tenant?->name ?? '');
    }

    // --- Organisatie (alleen admin) ---------------------------------------

    public function saveOrganisation(UpdateOrganisationAction $updateOrganisation, TenantLogoStorage $logoStorage): void
    {
        $tenant = auth()->user()->tenant;
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $request = new UpdateOrganisationRequest;
        $rules = ['orgName' => $request->rules()['name']];
        if ($this->orgLogo !== null) {
            $rules['orgLogo'] = ['nullable', 'image', 'max:2048'];
        }

        $validated = $this->validate(
            $rules,
            ['orgName.required' => __('team.errors.organisation_name_required')],
        );

        $payload = ['name' => $validated['orgName']];

        if ($this->orgLogo instanceof UploadedFile) {
            $logoStorage->delete($tenant->logo_path);
            $payload['logo_path'] = $logoStorage->store($this->orgLogo, (int) $tenant->id);
            $this->reset('orgLogo');
        }

        $updateOrganisation->handle($tenant, $payload, (int) auth()->id());

        $this->dispatch('saved');
    }

    // --- Collega-gebruikers (alleen admin) --------------------------------

    public function openCreateColleague(): void
    {
        $this->authorize('create', User::class);
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
        $this->colleagueRole = $user->role;
        $this->resetErrorBag();
        $this->showColleagueModal = true;
    }

    public function saveColleague(CreateColleagueAction $createColleague, UpdateColleagueAction $updateColleague): void
    {
        if ($this->editingColleagueId !== null) {
            $user = User::where('tenant_id', Tenancy::id())->findOrFail($this->editingColleagueId);
            $this->authorize('update', $user);

            $request = new UpdateColleagueRequest;
            $request->userId = $user->id;
            $validated = $this->validateColleague($request->rules(), $request->messages());

            $updateColleague->handle($user, $validated, (int) auth()->id());
        } else {
            $this->authorize('create', User::class);

            $request = new StoreColleagueRequest;
            $validated = $this->validateColleague($request->rules(), $request->messages());

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
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    private function validateColleague(array $rules, array $messages): array
    {
        $validated = $this->validate(
            [
                'colleagueName' => $rules['name'],
                'colleagueEmail' => $rules['email'],
                'colleagueRole' => $rules['role'],
            ],
            [
                'colleagueName.required' => $messages['name.required'] ?? '',
                'colleagueEmail.required' => $messages['email.required'] ?? '',
                'colleagueEmail.email' => $messages['email.email'] ?? '',
                'colleagueEmail.unique' => $messages['email.unique'] ?? '',
                'colleagueRole.required' => $messages['role.required'] ?? '',
                'colleagueRole.in' => $messages['role.in'] ?? '',
            ],
        );

        return [
            'name' => $validated['colleagueName'],
            'email' => $validated['colleagueEmail'],
            'role' => $validated['colleagueRole'],
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
        $this->reset(['colleagueName', 'colleagueEmail', 'colleagueRole', 'editingColleagueId']);
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
        $this->resetErrorBag();
        $this->showTeamModal = true;
    }

    public function saveTeam(CreateTeamAction $createTeam, UpdateTeamAction $updateTeam): void
    {
        $request = new StoreTeamRequest;
        $validated = $this->validate(
            [
                'teamName' => $request->rules()['name'],
                'teamSortOrder' => $request->rules()['sort_order'],
            ],
            ['teamName.required' => __('team.errors.team_name_required')],
        );

        if ($this->editingTeamId !== null) {
            $team = InternalTeam::findOrFail($this->editingTeamId);
            Gate::authorize('update', $team);

            // Actief-status wijzigen mag alleen een admin (= deactiveren-recht).
            $active = auth()->user()->can('deactivate', $team) ? $this->teamIsActive : $team->is_active;

            $updateTeam->handle($team, [
                'name' => $validated['teamName'],
                'sort_order' => $this->teamSortOrder,
                'is_active' => $active,
            ], (int) auth()->id());
        } else {
            Gate::authorize('create', InternalTeam::class);

            $createTeam->handle([
                'name' => $validated['teamName'],
                'sort_order' => $this->teamSortOrder,
                'is_active' => $this->teamIsActive,
            ], (int) Tenancy::id(), (int) auth()->id());
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

    public function cancelTeam(): void
    {
        $this->showTeamModal = false;
        $this->resetTeamForm();
    }

    private function resetTeamForm(): void
    {
        $this->reset(['teamName', 'teamSortOrder', 'teamIsActive', 'editingTeamId']);
        $this->teamIsActive = true;
        $this->resetErrorBag();
    }

    // --- Workers (admin of medewerker) ------------------------------------

    public function openAddWorker(int $teamId): void
    {
        $team = InternalTeam::findOrFail($teamId);
        Gate::authorize('update', $team);

        $this->reset(['workerFirstName', 'workerLastName']);
        $this->resetErrorBag(['workerFirstName', 'workerLastName']);
        $this->addingWorkerTeamId = $teamId;
    }

    public function saveWorker(CreateWorkerAction $createWorker): void
    {
        $team = InternalTeam::findOrFail((int) $this->addingWorkerTeamId);
        Gate::authorize('update', $team);

        $request = new StoreWorkerRequest;
        $validated = $this->validate(
            ['workerFirstName' => $request->rules()['first_name'], 'workerLastName' => $request->rules()['last_name']],
            ['workerFirstName.required' => __('team.errors.worker_name_required'), 'workerLastName.required' => __('team.errors.worker_name_required')],
        );

        $createWorker->handle($team, ['first_name' => $validated['workerFirstName'], 'last_name' => $validated['workerLastName']], (int) auth()->id());

        $this->reset(['workerFirstName', 'workerLastName', 'addingWorkerTeamId']);
        $this->dispatch('saved');
    }

    public function cancelWorker(): void
    {
        $this->reset(['workerFirstName', 'workerLastName', 'addingWorkerTeamId']);
        $this->resetErrorBag(['workerFirstName', 'workerLastName']);
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

    public function render()
    {
        $user = auth()->user();
        $canManageUsers = $user->can('create', User::class);

        $colleagues = $canManageUsers
            ? User::where('tenant_id', Tenancy::id())
                ->where('is_superuser', false)
                ->orderBy('name')
                ->get()
            : collect();

        $teams = InternalTeam::with(['workers' => fn ($q) => $q->orderBy('first_name')->orderBy('last_name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.pages.team', [
            'colleagues' => $colleagues,
            'teams' => $teams,
            'canManageUsers' => $canManageUsers,
            'canManageTeams' => $user->can('create', InternalTeam::class),
            'canEditContent' => $user->can('manageContent', InternalTeam::class),
            'roles' => User::ROLES,
        ]);
    }
}

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
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Team-hub (V2-spec §6): collega-gebruikers + organisatie (alleen admin),
 * operationele teams (+ team-QR) en workers (icoon-reset/lockout/actief/teamleader).
 * Dun: validatie via Form Requests, mutaties via Actions; RBAC via role + policy.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Team extends Component
{
    // Organisatie
    public string $orgName = '';

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

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    // --- Organisatie (alleen admin) ---------------------------------------

    public function saveOrganisation(UpdateOrganisationAction $updateOrganisation): void
    {
        $this->ensureAdmin();

        $tenant = auth()->user()->tenant;
        if ($tenant === null) {
            return;
        }

        $request = new UpdateOrganisationRequest;
        $validated = $this->validate(
            ['orgName' => $request->rules()['name']],
            ['orgName.required' => __('team.errors.organisation_name_required')],
        );

        $updateOrganisation->handle($tenant, ['name' => $validated['orgName']]);

        $this->dispatch('saved');
    }

    // --- Collega-gebruikers (alleen admin) --------------------------------

    public function openCreateColleague(): void
    {
        $this->ensureAdmin();
        $this->resetColleagueForm();
        $this->editingColleagueId = null;
        $this->showColleagueModal = true;
    }

    public function openEditColleague(int $id): void
    {
        $this->ensureAdmin();
        $user = User::where('tenant_id', Tenancy::id())->findOrFail($id);

        $this->editingColleagueId = $user->id;
        $this->colleagueName = $user->name;
        $this->colleagueEmail = $user->email;
        $this->colleagueRole = $user->role;
        $this->resetErrorBag();
        $this->showColleagueModal = true;
    }

    public function saveColleague(CreateColleagueAction $createColleague, UpdateColleagueAction $updateColleague): void
    {
        $this->ensureAdmin();

        if ($this->editingColleagueId !== null) {
            $user = User::where('tenant_id', Tenancy::id())->findOrFail($this->editingColleagueId);

            $request = new UpdateColleagueRequest;
            $request->userId = $user->id;
            $validated = $this->validateColleague($request->rules(), $request->messages());

            $updateColleague->handle($user, $validated);
        } else {
            $request = new StoreColleagueRequest;
            $validated = $this->validateColleague($request->rules(), $request->messages());

            $createColleague->handle($validated);
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
        $this->ensureAdmin();

        // Voorkom dat je je eigen account buitensluit.
        if ($id === auth()->id()) {
            return;
        }

        $user = User::where('tenant_id', Tenancy::id())->findOrFail($id);
        $setActive->handle($user, $active);
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
            $active = auth()->user()->isAdmin() ? $this->teamIsActive : $team->is_active;

            $updateTeam->handle($team, [
                'name' => $validated['teamName'],
                'sort_order' => $this->teamSortOrder,
                'is_active' => $active,
            ]);
        } else {
            Gate::authorize('create', InternalTeam::class);

            $createTeam->handle([
                'name' => $validated['teamName'],
                'sort_order' => $this->teamSortOrder,
                'is_active' => $this->teamIsActive,
            ]);
        }

        $this->showTeamModal = false;
        $this->resetTeamForm();
        $this->dispatch('saved');
    }

    public function setTeamActive(int $id, bool $active, SetTeamActiveAction $setActive): void
    {
        $team = InternalTeam::findOrFail($id);
        Gate::authorize('deactivate', $team);

        $setActive->handle($team, $active);
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

        $createWorker->handle($team, ['first_name' => $validated['workerFirstName'], 'last_name' => $validated['workerLastName']]);

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
        $resetIcon->handle($worker);
    }

    public function setWorkerActive(int $workerId, bool $active, SetWorkerActiveAction $setActive): void
    {
        $worker = $this->authorizedWorker($workerId);
        $setActive->handle($worker, $active);
    }

    public function setWorkerTeamleader(int $workerId, bool $isTeamleader, SetWorkerTeamleaderAction $setTeamleader): void
    {
        $worker = $this->authorizedWorker($workerId);
        $setTeamleader->handle($worker, $isTeamleader);
    }

    public function deleteWorker(int $workerId, DeleteWorkerAction $deleteWorker): void
    {
        $worker = $this->authorizedWorker($workerId);
        $deleteWorker->handle($worker);
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
        $canManageUsers = $user->isAdmin();

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
            'canManageTeams' => $user->isAdmin(),
            'canEditContent' => $user->isAdmin() || $user->isEmployee(),
            'roles' => User::ROLES,
        ]);
    }
}

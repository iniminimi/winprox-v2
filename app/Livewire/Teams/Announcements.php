<?php

namespace App\Livewire\Teams;

use App\Actions\Team\CreateTeamAnnouncementAction;
use App\Actions\Team\DeleteTeamAnnouncementAction;
use App\Actions\Team\UpdateTeamAnnouncementAction;
use App\Http\Requests\Team\StoreTeamAnnouncementRequest;
use App\Models\InternalTeam;
use App\Models\TeamAnnouncement;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Announcements extends Component
{
    use WithPagination;

    #[Locked]
    public InternalTeam $team;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingAnnouncementId = null;

    public string $description = '';

    public string $audience = 'team';

    public ?string $workerId = null;

    public bool $isActive = true;

    public ?string $expiresAt = null;

    public string $search = '';

    public string $audienceFilter = 'team';

    public string $statusFilter = 'active';

    public bool $panelOpen = false;

    public function mount(InternalTeam $team): void
    {
        $this->authorize('viewAny', [TeamAnnouncement::class, $team]);
        $this->team = $team;
    }

    public function updatedSearch(): void
    {
        $this->resetPage('announcementsPage');
    }

    public function updatedAudienceFilter(): void
    {
        $this->resetPage('announcementsPage');
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage('announcementsPage');
    }

    #[On('team-announcements-show-worker')]
    public function showWorker(int $workerId): void
    {
        $this->search = '';
        $this->audienceFilter = (string) $workerId;
        $this->statusFilter = 'active';
        $this->panelOpen = true;
        $this->resetPage('announcementsPage');
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', [TeamAnnouncement::class, $this->team]);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function createAnnouncement(CreateTeamAnnouncementAction $create): void
    {
        $this->authorize('create', [TeamAnnouncement::class, $this->team]);
        $this->normalizeAudience();
        $validated = $this->validate(StoreTeamAnnouncementRequest::fieldRules());

        $create->handle(
            $this->team,
            [
                'description' => $validated['description'],
                'worker_id' => $this->resolvedWorkerId($validated['audience'], $validated['workerId'] ?? null),
                'is_active' => (bool) $validated['isActive'],
                'expires_at' => $validated['expiresAt'] ?? null,
                'original_language' => app()->getLocale(),
            ],
            (int) Tenancy::id(),
            (int) auth()->id(),
        );

        $this->closeCreateModal();
    }

    public function openEditModal(int $announcementId): void
    {
        $announcement = $this->findAnnouncement($announcementId);
        $this->authorize('update', $announcement);
        $this->editingAnnouncementId = $announcement->id;
        $this->description = (string) $announcement->description;
        $this->audience = $announcement->worker_id === null ? 'team' : 'worker';
        $this->workerId = $announcement->worker_id === null ? null : (string) $announcement->worker_id;
        $this->isActive = (bool) $announcement->is_active;
        $this->expiresAt = $announcement->expires_at?->format('Y-m-d');
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingAnnouncementId = null;
        $this->resetForm();
    }

    public function updateAnnouncement(UpdateTeamAnnouncementAction $update): void
    {
        $announcement = $this->findAnnouncement((int) $this->editingAnnouncementId);
        $this->authorize('update', $announcement);
        $this->normalizeAudience();
        $validated = $this->validate(StoreTeamAnnouncementRequest::fieldRules());

        $update->handle(
            $announcement,
            [
                'description' => $validated['description'],
                'worker_id' => $this->resolvedWorkerId($validated['audience'], $validated['workerId'] ?? null),
                'is_active' => (bool) $validated['isActive'],
                'expires_at' => $validated['expiresAt'] ?? null,
            ],
            (int) Tenancy::id(),
            (int) auth()->id(),
        );

        $this->closeEditModal();
    }

    public function deleteAnnouncement(int $announcementId, DeleteTeamAnnouncementAction $delete): void
    {
        $announcement = $this->findAnnouncement($announcementId);
        $this->authorize('delete', $announcement);
        $delete->handle($announcement, (int) Tenancy::id(), (int) auth()->id());
    }

    public function render()
    {
        return view('livewire.teams.announcements', [
            'announcements' => TeamAnnouncement::query()
                ->where('internal_team_id', $this->team->id)
                ->forLibraryAudience($this->audienceFilter)
                ->withLibraryStatus($this->statusFilter)
                ->matchingLibrarySearch($this->search)
                ->with('worker')
                ->inLibraryOrder()
                ->paginate(25, pageName: 'announcementsPage'),
            'hasAnnouncements' => TeamAnnouncement::query()->where('internal_team_id', $this->team->id)->exists(),
            'workers' => Worker::query()
                ->where('internal_team_id', $this->team->id)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    private function findAnnouncement(int $announcementId): TeamAnnouncement
    {
        return TeamAnnouncement::query()
            ->where('internal_team_id', $this->team->id)
            ->whereKey($announcementId)
            ->firstOrFail();
    }

    private function resolvedWorkerId(string $audience, mixed $workerId): ?int
    {
        if ($audience !== 'worker') {
            return null;
        }

        $id = (int) $workerId;
        if ($id < 1 || ! Worker::query()->where('internal_team_id', $this->team->id)->whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                'workerId' => __('team.library.worker_required'),
            ]);
        }

        return $id;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->description = '';
        $this->audience = 'team';
        $this->workerId = null;
        $this->isActive = true;
        $this->expiresAt = null;
    }

    private function normalizeAudience(): void
    {
        if ($this->audience !== 'worker' || $this->workerId === '') {
            $this->workerId = null;
        }

        if ($this->expiresAt === '') {
            $this->expiresAt = null;
        }
    }
}

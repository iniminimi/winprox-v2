<?php

namespace App\Livewire\Teams;

use App\Actions\Team\CreateTeamDocumentAction;
use App\Actions\Team\DeleteTeamDocumentAction;
use App\Actions\Team\UpdateTeamDocumentAction;
use App\Actions\Team\ListTeamLibraryOpensAction;
use App\Http\Requests\Team\StoreTeamDocumentRequest;
use App\Models\InternalTeam;
use App\Models\TeamDocument;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Documents extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public InternalTeam $team;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    #[Locked]
    public ?int $opensItemId = null;

    public ?int $editingDocumentId = null;

    public string $description = '';

    public string $audience = 'team';

    public ?string $workerId = null;

    public bool $isActive = true;

    public ?TemporaryUploadedFile $documentFile = null;

    public string $search = '';

    public string $audienceFilter = 'all';

    public string $statusFilter = 'active';

    public bool $panelOpen = false;

    public function mount(InternalTeam $team, ?int $focusWorkerId = null): void
    {
        $this->authorize('viewAny', [TeamDocument::class, $team]);
        $this->team = $team;
        $this->focusWorker($focusWorkerId);
    }

    private function focusWorker(?int $workerId): void
    {
        if ($workerId === null || $workerId < 1) {
            return;
        }

        $onThisTeam = Worker::query()
            ->where('internal_team_id', $this->team->id)
            ->whereKey($workerId)
            ->exists();

        if ($onThisTeam) {
            $this->showWorker($workerId);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage('documentsPage');
    }

    public function updatedAudienceFilter(): void
    {
        $this->resetPage('documentsPage');
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage('documentsPage');
    }

    #[On('team-documents-show-worker')]
    public function showWorker(int $workerId): void
    {
        $this->search = '';
        $this->audienceFilter = (string) $workerId;
        $this->statusFilter = 'active';
        $this->panelOpen = true;
        $this->resetPage('documentsPage');
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', [TeamDocument::class, $this->team]);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function createDocument(CreateTeamDocumentAction $create): void
    {
        $this->authorize('create', [TeamDocument::class, $this->team]);
        $this->normalizeAudience();
        $validated = $this->validate(StoreTeamDocumentRequest::fieldRules(fileRequired: true));
        $workerId = $this->resolvedWorkerId($validated['audience'], $validated['workerId'] ?? null);

        $create->handle(
            $this->team,
            $this->documentFile,
            [
                'description' => $validated['description'],
                'worker_id' => $workerId,
                'is_active' => (bool) $validated['isActive'],
                'original_language' => app()->getLocale(),
            ],
            (int) Tenancy::id(),
            (int) auth()->id(),
        );

        $this->closeCreateModal();
    }

    public function openEditModal(int $documentId): void
    {
        $document = $this->findDocument($documentId);
        $this->authorize('update', $document);
        $this->editingDocumentId = $document->id;
        $this->description = (string) $document->description;
        $this->audience = $document->worker_id === null ? 'team' : 'worker';
        $this->workerId = $document->worker_id === null ? null : (string) $document->worker_id;
        $this->isActive = (bool) $document->is_active;
        $this->documentFile = null;
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingDocumentId = null;
        $this->resetForm();
    }

    public function updateDocument(UpdateTeamDocumentAction $update): void
    {
        $document = $this->findDocument((int) $this->editingDocumentId);
        $this->authorize('update', $document);
        $this->normalizeAudience();
        $validated = $this->validate(StoreTeamDocumentRequest::fieldRules(fileRequired: false));
        $workerId = $this->resolvedWorkerId($validated['audience'], $validated['workerId'] ?? null);

        $update->handle(
            $document,
            [
                'description' => $validated['description'],
                'worker_id' => $workerId,
                'is_active' => (bool) $validated['isActive'],
            ],
            $this->documentFile,
            (int) Tenancy::id(),
            (int) auth()->id(),
        );

        $this->closeEditModal();
    }

    public function deleteDocument(int $documentId, DeleteTeamDocumentAction $delete): void
    {
        $document = $this->findDocument($documentId);
        $this->authorize('delete', $document);
        $delete->handle($document, (int) Tenancy::id(), (int) auth()->id());
    }

    public function showOpens(int $itemId): void
    {
        $this->authorize('update', $this->findDocument($itemId));
        $this->opensItemId = $itemId;
    }

    public function closeOpens(): void
    {
        $this->opensItemId = null;
    }

    public function render(ListTeamLibraryOpensAction $listOpens)
    {
        $workers = Worker::query()
            ->where('internal_team_id', $this->team->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('livewire.teams.documents', [
            'documents' => TeamDocument::query()
                ->where('internal_team_id', $this->team->id)
                ->forLibraryAudience($this->audienceFilter)
                ->withLibraryStatus($this->statusFilter)
                ->matchingLibrarySearch($this->search)
                ->with('worker')
                ->inLibraryOrder()
                ->paginate(25, pageName: 'documentsPage'),
            'hasDocuments' => TeamDocument::query()->where('internal_team_id', $this->team->id)->exists(),
            'workers' => $workers,
            'activeWorkerIds' => $workers->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'opensRows' => $this->opensItemId !== null
                ? $listOpens->handle((int) Tenancy::id(), $this->findDocument($this->opensItemId))
                : null,
        ]);
    }

    private function findDocument(int $documentId): TeamDocument
    {
        return TeamDocument::query()
            ->where('internal_team_id', $this->team->id)
            ->whereKey($documentId)
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
        $this->documentFile = null;
    }

    private function normalizeAudience(): void
    {
        if ($this->audience !== 'worker' || $this->workerId === '') {
            $this->workerId = null;
        }
    }
}

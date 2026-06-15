<?php

namespace App\Livewire\Locations;

use App\Actions\Locations\CreateLocationDocumentAction;
use App\Actions\Locations\DeleteLocationDocumentAction;
use App\Actions\Locations\ToggleLocationDocumentActiveAction;
use App\Actions\Locations\UpdateLocationDocumentAction;
use App\Models\Document;
use App\Models\Location;
use App\Models\Unit;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use InvalidArgumentException;
use Throwable;

class Documents extends Component
{
    use WithFileUploads;

    public Location $location;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingDocumentId = null;

    public string $description = '';

    public string $unitId = '';

    public bool $isPublic = true;

    public bool $requiresVerification = false;

    public bool $isActive = true;

    public string $search = '';

    public ?TemporaryUploadedFile $documentFile = null;

    public ?TemporaryUploadedFile $editDocumentFile = null;

    public string $currentFileName = '';

    public function mount(Location $location): void
    {
        $this->authorize('view', $location);
        $this->location = $location;
    }

    protected function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'unitId' => ['nullable', 'string'],
            'isPublic' => ['boolean'],
            'requiresVerification' => ['boolean'],
            'isActive' => ['boolean'],
            'documentFile' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'description.required' => __('validation.required'),
            'description.min' => __('validation.min.string', ['min' => 3]),
            'documentFile.required' => __('locations.documents.errors.file_required'),
            'documentFile.mimes' => __('locations.documents.errors.file_mimes'),
            'documentFile.max' => __('locations.documents.errors.file_max'),
            'editDocumentFile.mimes' => __('locations.documents.errors.file_mimes'),
            'editDocumentFile.max' => __('locations.documents.errors.file_max'),
        ];
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', [Document::class, $this->location]);
        $this->resetValidation();
        $this->description = '';
        $this->unitId = '';
        $this->isPublic = true;
        $this->requiresVerification = false;
        $this->isActive = true;
        $this->documentFile = null;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->resetValidation();
        $this->showCreateModal = false;
    }

    public function openEditModal(int $documentId): void
    {
        $document = $this->findDocument($documentId);
        $this->authorize('update', $document);

        $this->resetValidation();
        $this->editingDocumentId = $document->id;
        $this->description = (string) ($document->description ?? '');
        $this->unitId = $document->unit_id ? (string) $document->unit_id : '';
        $this->isPublic = (bool) $document->is_public;
        $this->requiresVerification = (bool) $document->requires_verification;
        $this->isActive = (bool) $document->is_active;
        $this->editDocumentFile = null;
        $this->currentFileName = basename((string) $document->file_path);
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->resetValidation();
        $this->showEditModal = false;
        $this->editingDocumentId = null;
        $this->editDocumentFile = null;
        $this->currentFileName = '';
    }

    public function createDocument(CreateLocationDocumentAction $create): void
    {
        $this->authorize('create', [Document::class, $this->location]);
        $this->description = trim($this->description);
        $validated = $this->validate();
        $tenantId = (int) auth()->user()->tenant_id;

        if (! $this->isValidUnitId($tenantId, $validated['unitId'] ?? '')) {
            $this->addError('unitId', __('locations.documents.errors.invalid_unit'));

            return;
        }

        try {
            $result = $create->handle(
                $this->location,
                $validated['documentFile'],
                [
                    'description' => $validated['description'],
                    'unit_id' => $this->parsedUnitId($validated['unitId'] ?? ''),
                    'is_public' => (bool) $validated['isPublic'],
                    'requires_verification' => (bool) $validated['requiresVerification'],
                    'is_active' => (bool) $validated['isActive'],
                ],
                $tenantId,
                (int) auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('documentFile', __($this->documentLimitMessageKey($e->getMessage())));

            return;
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', __('locations.documents.flash.upload_failed'));

            return;
        }

        $this->closeCreateModal();
        session()->flash('success', __('locations.documents.flash.created'));
        if ($result['metadata_partial']) {
            session()->flash('warning', __('locations.documents.flash.metadata_partial'));
        }
    }

    public function updateDocument(UpdateLocationDocumentAction $update): void
    {
        if ($this->editingDocumentId === null) {
            return;
        }

        $document = $this->findDocument($this->editingDocumentId);
        $this->authorize('update', $document);

        $this->description = trim($this->description);
        $validated = $this->validate([
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'unitId' => ['nullable', 'string'],
            'isPublic' => ['boolean'],
            'requiresVerification' => ['boolean'],
            'isActive' => ['boolean'],
            'editDocumentFile' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ], $this->messages());

        $tenantId = (int) auth()->user()->tenant_id;
        if (! $this->isValidUnitId($tenantId, $validated['unitId'] ?? '')) {
            $this->addError('unitId', __('locations.documents.errors.invalid_unit'));

            return;
        }

        try {
            $result = $update->handle(
                $this->location,
                $document,
                [
                    'description' => $validated['description'],
                    'unit_id' => $this->parsedUnitId($validated['unitId'] ?? ''),
                    'is_public' => (bool) $validated['isPublic'],
                    'requires_verification' => (bool) $validated['requiresVerification'],
                    'is_active' => (bool) $validated['isActive'],
                ],
                $validated['editDocumentFile'] ?? null,
                $tenantId,
                (int) auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('unitId', __($this->documentLimitMessageKey($e->getMessage())));

            return;
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', __('locations.documents.flash.upload_failed'));

            return;
        }

        $this->closeEditModal();
        session()->flash('success', __('locations.documents.flash.updated'));
        if ($result['metadata_partial']) {
            session()->flash('warning', __('locations.documents.flash.metadata_partial'));
        }
    }

    public function deleteDocument(int $documentId, DeleteLocationDocumentAction $delete): void
    {
        $document = $this->findDocument($documentId);
        $this->authorize('delete', $document);
        $delete->handle($document, (int) auth()->id());
        session()->flash('success', __('locations.documents.flash.deleted'));
    }

    public function toggleDocumentActive(int $documentId, ToggleLocationDocumentActiveAction $toggle): void
    {
        $document = $this->findDocument($documentId);
        $this->authorize('update', $document);
        $toggle->handle($document, (int) auth()->id());
        session()->flash('success', __('locations.documents.flash.updated'));
    }

    public function render()
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $documents = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $this->location->id)
            ->when(trim($this->search) !== '', function ($query) {
                $search = '%'.trim($this->search).'%';
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            })
            ->with('unit:id,name')
            ->latest()
            ->get();

        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $this->location->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.locations.documents', [
            'documents' => $documents,
            'units' => $units,
        ]);
    }

    private function findDocument(int $documentId): Document
    {
        return Document::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('location_id', $this->location->id)
            ->findOrFail($documentId);
    }

    private function isValidUnitId(int $tenantId, string $unitId): bool
    {
        if ($unitId === '') {
            return true;
        }

        if (! ctype_digit($unitId)) {
            return false;
        }

        return Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $this->location->id)
            ->whereKey((int) $unitId)
            ->exists();
    }

    private function parsedUnitId(string $unitId): ?int
    {
        return $unitId !== '' ? (int) $unitId : null;
    }

    private function documentLimitMessageKey(string $code): string
    {
        return match ($code) {
            'document_org_limit_exceeded' => 'locations.documents.errors.org_limit',
            'document_unit_limit_exceeded' => 'locations.documents.errors.unit_limit',
            default => 'locations.documents.flash.upload_failed',
        };
    }
}

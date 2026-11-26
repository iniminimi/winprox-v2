<?php

namespace App\Livewire\Locations;

use App\Actions\Locations\CreateLocationAnnouncementAction;
use App\Actions\Locations\DeleteLocationAnnouncementAction;
use App\Actions\Locations\ToggleLocationAnnouncementActiveAction;
use App\Actions\Locations\UpdateLocationAnnouncementAction;
use App\Models\Announcement;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use InvalidArgumentException;
use Livewire\Component;

class Announcements extends Component
{
    public Location $location;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingAnnouncementId = null;

    public string $description = '';

    public string $unitId = '';

    public bool $isActive = true;

    public string $expiresAt = '';

    public string $previewLocale = '';

    public function mount(Location $location): void
    {
        $this->authorize('view', $location);
        $this->location = $location;
        $this->previewLocale = LocaleSupport::normalize(app()->getLocale());
    }

    protected function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:'.TextDescriptionLimits::MAX],
            'unitId' => ['nullable', 'string'],
            'isActive' => ['boolean'],
            'expiresAt' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'description.max' => __('issues.errors.text_max'),
        ];
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', [Announcement::class, $this->location]);
        $this->resetValidation();
        $this->description = '';
        $this->unitId = '';
        $this->isActive = true;
        $this->expiresAt = '';
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->resetValidation();
        $this->showCreateModal = false;
    }

    public function openEditModal(int $announcementId): void
    {
        $announcement = $this->findAnnouncement($announcementId);
        $this->authorize('update', $announcement);

        $this->resetValidation();
        $this->editingAnnouncementId = $announcement->id;
        $this->description = (string) $announcement->description;
        $this->unitId = $announcement->unit_id ? (string) $announcement->unit_id : '';
        $this->isActive = (bool) $announcement->is_active;
        $this->expiresAt = $announcement->expires_at?->format('Y-m-d') ?? '';
        $this->previewLocale = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->resetValidation();
        $this->showEditModal = false;
        $this->editingAnnouncementId = null;
    }

    public function createAnnouncement(CreateLocationAnnouncementAction $create): void
    {
        $this->authorize('create', [Announcement::class, $this->location]);
        $validated = $this->validate();
        $tenantId = (int) auth()->user()->tenant_id;

        if (! $this->isValidUnitId($tenantId, $validated['unitId'] ?? '')) {
            $this->addError('unitId', __('locations.announcements.errors.invalid_unit'));

            return;
        }

        try {
            $create->handle($this->location, [
                'description' => trim((string) $validated['description']),
                'unit_id' => $this->parsedUnitId($validated['unitId'] ?? ''),
                'is_active' => (bool) $validated['isActive'],
                'expires_at' => $validated['expiresAt'] ?: null,
                'original_language' => auth()->user()?->locale,
            ], $tenantId, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'announcement_unit_limit_exceeded') {
                $this->addError('unitId', __('locations.announcements.errors.unit_limit'));
            }

            return;
        }

        $this->closeCreateModal();
        session()->flash('success', __('locations.announcements.flash.created'));
    }

    public function updateAnnouncement(UpdateLocationAnnouncementAction $update): void
    {
        if ($this->editingAnnouncementId === null) {
            return;
        }

        $announcement = $this->findAnnouncement($this->editingAnnouncementId);
        $this->authorize('update', $announcement);
        $validated = $this->validate();
        $tenantId = (int) auth()->user()->tenant_id;

        if (! $this->isValidUnitId($tenantId, $validated['unitId'] ?? '')) {
            $this->addError('unitId', __('locations.announcements.errors.invalid_unit'));

            return;
        }

        try {
            $update->handle($this->location, $announcement, [
                'description' => trim((string) $validated['description']),
                'unit_id' => $this->parsedUnitId($validated['unitId'] ?? ''),
                'is_active' => (bool) $validated['isActive'],
                'expires_at' => $validated['expiresAt'] ?: null,
            ], $tenantId, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'announcement_unit_limit_exceeded') {
                $this->addError('unitId', __('locations.announcements.errors.unit_limit'));
            }

            return;
        }

        $this->closeEditModal();
        session()->flash('success', __('locations.announcements.flash.updated'));
    }

    public function deleteAnnouncement(int $announcementId, DeleteLocationAnnouncementAction $delete): void
    {
        $announcement = $this->findAnnouncement($announcementId);
        $this->authorize('delete', $announcement);
        $delete->handle($announcement, (int) auth()->id());
        session()->flash('success', __('locations.announcements.flash.deleted'));
    }

    public function toggleAnnouncementActive(int $announcementId, ToggleLocationAnnouncementActiveAction $toggle): void
    {
        $announcement = $this->findAnnouncement($announcementId);
        $this->authorize('update', $announcement);

        try {
            $toggle->handle($announcement, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'announcement_unit_limit_exceeded') {
                session()->flash('error', __('locations.announcements.errors.unit_limit'));
            }

            return;
        }

        session()->flash('success', __('locations.announcements.flash.updated'));
    }

    public function render()
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $announcements = Announcement::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $this->location->id)
            ->with(['unit.translations', 'translations'])
            ->latest()
            ->get();

        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $this->location->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $editingAnnouncement = null;
        $previewDescription = '';
        $previewDescriptionMissing = false;

        if ($this->showEditModal && $this->editingAnnouncementId !== null) {
            $editingAnnouncement = Announcement::query()
                ->where('tenant_id', $tenantId)
                ->where('location_id', $this->location->id)
                ->with('translations')
                ->find($this->editingAnnouncementId);

            if ($editingAnnouncement !== null && $editingAnnouncement->is_active) {
                $displayLocale = LocaleSupport::normalize($this->previewLocale);
                $previewDescription = $editingAnnouncement->descriptionForDisplayLocale($displayLocale);
                $previewDescriptionMissing = $editingAnnouncement->descriptionMissingForDisplayLocale($displayLocale);
            }
        }

        return view('livewire.locations.announcements', [
            'announcements' => $announcements,
            'units' => $units,
            'editingAnnouncement' => $editingAnnouncement,
            'previewDescription' => $previewDescription,
            'previewDescriptionMissing' => $previewDescriptionMissing,
            'descriptionLocales' => config('locales.labels', []),
        ]);
    }

    private function findAnnouncement(int $announcementId): Announcement
    {
        return Announcement::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('location_id', $this->location->id)
            ->findOrFail($announcementId);
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
}

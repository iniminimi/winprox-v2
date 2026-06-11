<?php

namespace App\Livewire\Locations;

use App\Actions\Categories\SyncCategoryTeamsAction;
use App\Actions\Locations\ActivateLocationAction;
use App\Actions\Locations\CreateCategoryAction;
use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Actions\Locations\DeleteCategoryAction;
use App\Actions\Locations\UpdateCategoryAction;
use App\Actions\Locations\UpdateLocationAction;
use App\Actions\Units\ImportUnitsAction;
use App\Data\Units\ImportUnitsData;
use App\Http\Requests\Locations\StoreCategoryRequest;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateCategoryRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Http\Requests\Units\ImportUnitsRequest;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    use WithFileUploads;
    #[Url(as: 'q')]
    public string $search = '';

    public bool $showInactive = false;

    public bool $showModal = false;

    public ?int $editingLocationId = null;

    public string $locationFormName = '';

    public string $locationFormStreet = '';

    public string $locationFormHouseNumber = '';

    public string $locationFormPostalCode = '';

    public string $locationFormCity = '';

    public string $locationFormCountryCode = 'BE';

    public string $locationFormNotes = '';

    public $importFile = null;

    public array $importErrors = [];

    public ?int $importedCount = null;

    public bool $showImportModal = false;

    public bool $showCategoriesModal = false;

    public bool $showCategoriesSection = false;

    public ?int $editingCategoryId = null;

    public string $categoryName = '';

    /** @var array<int, int> */
    public array $selectedCategoryTeamIds = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);
    }

    public function updatedSearch(): void
    {
        // Re-render list.
    }

    public function openCreate(): void
    {
        $this->authorize('create', Location::class);
        $this->resetForm();
        $this->editingLocationId = null;
        $this->showModal = true;
    }

    public function openEdit(int $locationId): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('update', $location);

        $this->editingLocationId = $location->id;
        $this->locationFormName = (string) $location->name;
        $this->locationFormStreet = (string) ($location->street ?? $location->address ?? '');
        $this->locationFormHouseNumber = (string) ($location->house_number ?? '');
        $this->locationFormPostalCode = (string) ($location->postal_code ?? '');
        $this->locationFormCity = (string) ($location->city ?? '');
        $this->locationFormCountryCode = (string) ($location->country_code ?? 'BE');
        $this->locationFormNotes = (string) ($location->notes ?? '');
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(
        CreateLocationAction $createLocation,
        UpdateLocationAction $updateLocation,
    ): void {
        $payload = $this->locationFormPayload();

        if ($this->editingLocationId === null) {
            $this->authorize('create', Location::class);
            $validated = StoreLocationRequest::validatePayload($payload);
            $createLocation->handle($validated, (int) auth()->user()->tenant_id, (int) auth()->id());
            session()->flash('success', __('locations.flash.created'));
        } else {
            $location = Location::findOrFail($this->editingLocationId);
            $this->authorize('update', $location);
            $validated = UpdateLocationRequest::validatePayload($payload);
            $updateLocation->handle($location, $validated, (int) auth()->id());
            session()->flash('success', __('locations.flash.updated'));
        }

        $this->closeModal();
    }

    public function deactivate(int $locationId, DeactivateLocationAction $deactivateLocation): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('deactivate', $location);
        $deactivateLocation->handle($location, (int) auth()->id());
        session()->flash('success', __('locations.flash.deactivated'));
    }

    public function activate(int $locationId, ActivateLocationAction $activateLocation): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('update', $location);
        $activateLocation->handle($location, (int) auth()->id());
        session()->flash('success', __('locations.flash.activated'));
    }

    public function openImportModal(): void
    {
        $this->authorize('create', Location::class);
        $this->importFile = null;
        $this->importErrors = [];
        $this->importedCount = null;
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importErrors = [];
        $this->importedCount = null;
    }

    public function importUnits(ImportUnitsAction $importUnits): void
    {
        $this->authorize('create', Location::class);

        if ($this->importFile === null) {
            $this->importErrors = ['Er moet een bestand worden geüpload.'];
            return;
        }

        // Validate using reusable rules from Form Request
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['file' => $this->importFile],
            ImportUnitsRequest::getReusableRules(),
            ImportUnitsRequest::getReusableMessages()
        );

        if ($validator->fails()) {
            $this->importErrors = $validator->errors()->all();
            return;
        }

        // Build DTO from validated file
        $dto = new ImportUnitsData(
            filePath: $this->importFile->getRealPath(),
            originalName: $this->importFile->getClientOriginalName(),
        );

        // Call Action with explicit context
        $result = $importUnits->handle(
            $dto,
            Tenancy::id(),
            (int) auth()->id()
        );

        if ($result['success']) {
            $this->importedCount = $result['count'];
            $this->importErrors = [];
            session()->flash('success', __('locations.flash.imported', ['count' => $result['count']]));
            $this->closeImportModal();
        } else {
            $this->importErrors = $result['errors'];
        }
    }

    public function downloadSampleCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('create', Location::class);

        $headers = [
            'location_name',
            'street',
            'house_number',
            'postal_code',
            'city',
            'country_code',
            'notes',
            'unit_name',
            'description',
            'category_name',
        ];

        // Localized sample row
        $sampleRow = [
            __('locations.import_sample.sample_location_name'),
            __('locations.import_sample.sample_street'),
            __('locations.import_sample.sample_house_number'),
            __('locations.import_sample.sample_postal_code'),
            __('locations.import_sample.sample_city'),
            __('locations.import_sample.sample_country_code'),
            __('locations.import_sample.sample_notes'),
            __('locations.import_sample.sample_unit_name'),
            __('locations.import_sample.sample_description'),
            __('locations.import_sample.sample_category_name'),
        ];

        return response()->streamDownload(function () use ($headers, $sampleRow) {
            // UTF-8 BOM for Excel compatibility
            echo "\xEF\xBB\xBF";

            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fputcsv($file, $sampleRow);
            fclose($file);
        }, 'winprox_sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function locationFormPayload(): array
    {
        return [
            'name' => $this->locationFormName,
            'street' => $this->locationFormStreet,
            'house_number' => $this->locationFormHouseNumber,
            'postal_code' => $this->locationFormPostalCode,
            'city' => $this->locationFormCity,
            'country_code' => $this->locationFormCountryCode,
            'notes' => $this->locationFormNotes,
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'locationFormName', 'locationFormStreet', 'locationFormHouseNumber', 'locationFormPostalCode', 'locationFormCity', 'locationFormNotes', 'editingLocationId',
        ]);
        $this->editingLocationId = null;
        $this->locationFormCountryCode = 'BE';
        $this->resetErrorBag();
    }

    public function openCategoriesModal(): void
    {
        $this->authorize('create', Category::class);
        $this->resetCategoryForm();
        $this->showCategoriesModal = true;
    }

    public function closeCategoriesModal(): void
    {
        $this->showCategoriesModal = false;
        $this->resetCategoryForm();
        $this->resetErrorBag();
    }

    public function openEditCategory(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->authorize('update', $category);
        $this->editingCategoryId = (int) $category->id;
        $this->categoryName = (string) $category->name;
        $this->selectedCategoryTeamIds = $category->teams()->pluck('internal_teams.id')->toArray();
        $this->showCategoriesModal = true;
        $this->resetErrorBag();
    }

    public function cancelEditCategory(): void
    {
        $this->resetCategoryForm();
        $this->resetErrorBag();
    }

    public function saveCategory(CreateCategoryAction $createCategory, UpdateCategoryAction $updateCategory, SyncCategoryTeamsAction $syncTeams): void
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $rules = $this->editingCategoryId === null
            ? StoreCategoryRequest::ruleSet($tenantId)
            : UpdateCategoryRequest::ruleSetFor($tenantId, $this->editingCategoryId);

        $validated = $this->validate([
            'categoryName' => $rules['name'],
            'selectedCategoryTeamIds' => 'required|array|min:1',
            'selectedCategoryTeamIds.*' => 'exists:internal_teams,id',
        ], [
            'categoryName.required' => __('locations.categories.errors.name_required'),
            'categoryName.unique' => __('locations.categories.errors.duplicate_name'),
            'selectedCategoryTeamIds.required' => __('locations.categories.errors.teams_required'),
            'selectedCategoryTeamIds.min' => __('locations.categories.errors.teams_required'),
        ]);

        if ($this->editingCategoryId === null) {
            $this->authorize('create', Category::class);
            $category = $createCategory->handle($tenantId, ['name' => $validated['categoryName']], (int) auth()->id());
        } else {
            $category = Category::query()->findOrFail($this->editingCategoryId);
            $this->authorize('update', $category);
            $updateCategory->handle($category, ['name' => $validated['categoryName']], (int) auth()->id());
        }

        $this->authorize('syncTeams', $category);
        $syncTeams->handle(
            $category,
            \App\Data\Categories\SyncCategoryTeamsData::fromRequest(['teams' => $validated['selectedCategoryTeamIds']]),
            auth()->user(),
        );

        $this->resetCategoryForm();
    }

    public function deleteCategory(int $categoryId, DeleteCategoryAction $deleteCategory): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->authorize('delete', $category);
        $deleteCategory->handle($category, (int) auth()->id());
    }

    private function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->selectedCategoryTeamIds = [];
    }

    public function render()
    {
        $term = trim($this->search);

        $locations = Location::query()
            ->withCount('units')
            ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('street', 'like', $like)
                        ->orWhere('house_number', 'like', $like)
                        ->orWhere('postal_code', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('address', 'like', $like);
                });
            })
            ->orderBy('name')
            ->get();

        $hasInactiveLocations = Location::query()->where('is_active', false)->exists();

        $categoriesEnabled = Schema::hasTable('categories');

        $teams = InternalTeam::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = $categoriesEnabled
            ? Category::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.locations.index', [
            'locations' => $locations,
            'hasInactiveLocations' => $hasInactiveLocations,
            'teams' => $teams,
            'categories' => $categories,
            'onboarding' => TenantOnboardingState::current(),
        ]);
    }
}

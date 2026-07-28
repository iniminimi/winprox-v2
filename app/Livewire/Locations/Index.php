<?php

namespace App\Livewire\Locations;

use App\Actions\Categories\SyncCategoryTeamsAction;
use App\Actions\Communication\ImportCategoryTranslationsAction;
use App\Actions\Locations\ActivateLocationAction;
use App\Actions\Locations\CreateCategoryAction;
use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Actions\Locations\DeleteCategoryAction;
use App\Actions\Locations\UpdateCategoryAction;
use App\Actions\Locations\UpdateLocationAction;
use App\Http\Requests\Locations\StoreCategoryRequest;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateCategoryRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
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

    public bool $showCategoriesModal = false;

    public bool $showCategoriesSection = false;

    public ?int $editingCategoryId = null;

    public string $categoryName = '';

    public bool $categoryAllowGpsLocation = false;

    public bool $categoryIsReservable = false;

    /** @var array<int, int> */
    public array $selectedCategoryTeamIds = [];

    public string $categoryPreviewLocale = '';

    public string $categoryTranslationName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);

        $categoryId = (int) request()->query('edit_category', 0);
        if ($categoryId > 0) {
            $this->showCategoriesSection = true;
            $this->openEditCategory($categoryId);
        }
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
            $validated['original_language'] = auth()->user()?->locale;
            try {
                $createLocation->handle($validated, (int) auth()->user()->tenant_id, (int) auth()->id());
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() === 'location_limit_exceeded') {
                    $this->addError('locationFormName', __('locations.errors.location_limit'));

                    return;
                }

                throw $e;
            }
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
        $this->categoryAllowGpsLocation = (bool) $category->allow_gps_location;
        $this->categoryIsReservable = (bool) $category->is_reservable;
        $this->selectedCategoryTeamIds = $category->teams()->pluck('internal_teams.id')->toArray();
        $this->categoryPreviewLocale = $this->defaultTranslationLocaleForCategory($category);
        $this->hydrateCategoryTranslationInput($category->fresh('translations'));
        $this->showCategoriesModal = true;
        $this->resetErrorBag();
    }

    public function updatedCategoryPreviewLocale(): void
    {
        if ($this->editingCategoryId === null) {
            $this->categoryTranslationName = '';

            return;
        }

        $category = Category::query()
            ->with('translations')
            ->find($this->editingCategoryId);

        $this->hydrateCategoryTranslationInput($category);
    }

    public function cancelEditCategory(): void
    {
        $this->closeCategoriesModal();
    }

    public function saveCategory(CreateCategoryAction $createCategory, UpdateCategoryAction $updateCategory, SyncCategoryTeamsAction $syncTeams): void
    {
        $tenantId = (int) auth()->user()->tenant_id;

        $rules = $this->editingCategoryId === null
            ? StoreCategoryRequest::ruleSet($tenantId)
            : UpdateCategoryRequest::ruleSetFor($tenantId, $this->editingCategoryId);

        $validated = $this->validate([
            'categoryName' => $rules['name'],
            'categoryAllowGpsLocation' => $rules['allow_gps_location'],
            'categoryIsReservable' => $rules['is_reservable'],
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
            $category = $createCategory->handle($tenantId, [
                'name' => $validated['categoryName'],
                'allow_gps_location' => (bool) $validated['categoryAllowGpsLocation'],
                'is_reservable' => (bool) $validated['categoryIsReservable'],
                'original_language' => auth()->user()?->locale,
            ], (int) auth()->id());
        } else {
            $category = Category::query()->findOrFail($this->editingCategoryId);
            $this->authorize('update', $category);
            $updateCategory->handle($category, [
                'name' => $validated['categoryName'],
                'allow_gps_location' => (bool) $validated['categoryAllowGpsLocation'],
                'is_reservable' => (bool) $validated['categoryIsReservable'],
            ], (int) auth()->id());
        }

        $this->authorize('syncTeams', $category);
        $syncTeams->handle(
            $category,
            \App\Data\Categories\SyncCategoryTeamsData::fromRequest(['teams' => $validated['selectedCategoryTeamIds']]),
            auth()->user(),
        );

        $this->closeCategoriesModal();
    }

    public function saveCategoryTranslationOverride(ImportCategoryTranslationsAction $importCategoryTranslations): void
    {
        if ($this->editingCategoryId === null) {
            return;
        }

        $category = Category::query()
            ->with('translations')
            ->findOrFail($this->editingCategoryId);

        $this->authorize('update', $category);

        $validated = $this->validate([
            'categoryTranslationName' => ['required', 'string', 'max:255'],
        ]);

        $locale = LocaleSupport::normalize($this->categoryPreviewLocale);
        if ($locale === $category->normalizedOriginalLanguage()) {
            $this->addError('categoryTranslationName', __('issues.errors.translation_same_as_source'));

            return;
        }

        $name = trim((string) $validated['categoryTranslationName']);
        if ($name === '') {
            $this->addError('categoryTranslationName', __('issues.errors.translation_import_invalid'));

            return;
        }

        try {
            $importCategoryTranslations->handle([
                [
                    'category_id' => $category->id,
                    'locale' => $locale,
                    'name' => $name,
                ],
            ], (int) auth()->id());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                if (! is_array($messages)) {
                    continue;
                }

                foreach ($messages as $message) {
                    $this->addError('categoryTranslationName', (string) $message);
                }
            }

            return;
        }

        $this->hydrateCategoryTranslationInput($category->fresh('translations'));
        session()->flash('success', __('locations.categories.flash.translation_saved'));
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
        $this->categoryAllowGpsLocation = false;
        $this->categoryIsReservable = false;
        $this->selectedCategoryTeamIds = [];
        $this->categoryPreviewLocale = '';
        $this->categoryTranslationName = '';
    }

    private function hydrateCategoryTranslationInput(?Category $category): void
    {
        if ($category === null) {
            $this->categoryTranslationName = '';

            return;
        }

        $locale = LocaleSupport::normalize($this->categoryPreviewLocale);
        if ($locale === $category->normalizedOriginalLanguage()) {
            $locale = $this->defaultTranslationLocaleForCategory($category);
            $this->categoryPreviewLocale = $locale;
        }

        $translation = $category->translations
            ->first(fn ($row) => $row->locale === $locale);

        $this->categoryTranslationName = (string) ($translation?->name ?? '');
    }

    private function defaultTranslationLocaleForCategory(Category $category): string
    {
        $targets = LocaleSupport::targetLocalesForSource($category->normalizedOriginalLanguage());
        $preferred = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

        if (in_array($preferred, $targets, true)) {
            return $preferred;
        }

        return $targets[0] ?? $preferred;
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

        $hasAnyLocation = Location::query()->exists();

        $categoriesEnabled = Schema::hasTable('categories');

        $teams = InternalTeam::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'original_language']);

        $categories = $categoriesEnabled
            ? Category::query()->with('translations')->orderBy('name')->get(['id', 'name', 'original_language'])
            : collect();

        $categoryTranslationLocales = config('locales.labels', []);
        if ($this->showCategoriesModal && $this->editingCategoryId !== null) {
            $editingCategory = Category::query()->find($this->editingCategoryId);

            if ($editingCategory !== null) {
                $sourceLocale = $editingCategory->normalizedOriginalLanguage();
                $categoryTranslationLocales = array_filter(
                    $categoryTranslationLocales,
                    fn (string $label, string $code): bool => $code !== $sourceLocale,
                    ARRAY_FILTER_USE_BOTH,
                );
            }
        }

        return view('livewire.locations.index', [
            'locations' => $locations,
            'hasAnyLocation' => $hasAnyLocation,
            'hasInactiveLocations' => $hasInactiveLocations,
            'teams' => $teams,
            'categories' => $categories,
            'categoryTranslationLocales' => $categoryTranslationLocales,
            'onboarding' => TenantOnboardingState::current(),
        ]);
    }
}

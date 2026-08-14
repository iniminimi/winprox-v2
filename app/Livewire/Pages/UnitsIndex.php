<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Models\Category;
use App\Models\Location;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class UnitsIndex extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    #[Url(as: 'category')]
    public ?int $categoryFilter = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Unit::class);
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $locations = Location::query()
            ->orderBy('name')
            ->get(['id', 'name', 'address']);

        $categories = Category::query()
            ->with('translations')
            ->orderBy('name')
            ->get(['id', 'name', 'original_language']);

        $query = Unit::query()
            ->with([
                'location',
                'category',
                'translations',
                'category.translations',
            ]);

        if ($this->locationFilter !== null) {
            $query->where('location_id', $this->locationFilter);
        }

        if ($this->categoryFilter !== null) {
            $query->where('category_id', $this->categoryFilter);
        }

        $units = $query
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.pages.units-index', [
            'units' => $units,
            'locations' => $locations,
            'categories' => $categories,
        ]);
    }
}


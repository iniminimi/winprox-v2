<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Enums\UnitCheckResult;
use App\Models\Location;
use App\Models\UnitCheck;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class UnitChecksIndex extends Component
{
    use WithPagination;

    #[Url(as: 'result')]
    public string $resultFilter = 'all';

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    public function mount(): void
    {
        $this->authorize('viewAny', UnitCheck::class);

        if (! in_array($this->resultFilter, ['all', ...UnitCheckResult::values()], true)) {
            $this->resultFilter = 'all';
        }
    }

    public function updatedResultFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = UnitCheck::query()
            ->with(['unit', 'location', 'worker', 'team'])
            ->orderByDesc('checked_at')
            ->orderByDesc('id');

        if ($this->resultFilter !== 'all') {
            $query->where('result', $this->resultFilter);
        }

        if ($this->locationFilter !== null) {
            $query->where('location_id', $this->locationFilter);
        }

        $checks = $query->paginate(25);
        $groupedChecks = $this->groupChecksByDay($checks->getCollection());

        return view('livewire.pages.unit-checks-index', [
            'checks' => $checks,
            'groupedChecks' => $groupedChecks,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'address']),
        ]);
    }

    /**
     * @param  Collection<int, UnitCheck>  $checks
     * @return Collection<int, array{key: string, label: string, checks: Collection<int, UnitCheck>}>
     */
    private function groupChecksByDay(Collection $checks): Collection
    {
        return $checks
            ->groupBy(fn (UnitCheck $check) => (string) $check->checked_at?->toDateString())
            ->map(function (Collection $group, string $dayKey): array {
                $date = CarbonImmutable::parse($dayKey);

                return [
                    'key' => $dayKey,
                    'label' => $date->translatedFormat('l d-m-Y'),
                    'checks' => $group->values(),
                ];
            })
            ->values();
    }
}

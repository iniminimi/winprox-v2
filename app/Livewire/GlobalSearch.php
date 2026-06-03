<?php

namespace App\Livewire;

use App\Actions\Search\SearchTenantGlobalAction;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public bool $isOpen = false;

    #[Locked]
    public int $minQueryLength = 2;

    public function updatedQuery(): void
    {
        $this->isOpen = mb_strlen(trim($this->query)) >= $this->minQueryLength;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->query = '';
    }

    public function render(SearchTenantGlobalAction $searchTenantGlobal)
    {
        $results = collect();

        $user = auth()->user();
        $tenantId = Tenancy::id();

        if ($user !== null && $tenantId !== null && mb_strlen(trim($this->query)) >= $this->minQueryLength) {
            /** @var Collection<string, Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>> $results */
            $results = $searchTenantGlobal->handle($user, $tenantId, trim($this->query));
        }

        return view('livewire.global-search', [
            'results' => $results,
        ]);
    }
}

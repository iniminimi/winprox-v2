<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Actions\Audit\ListPlatformAuditLogsAction;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Audit extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('accessPlatform', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(ListPlatformAuditLogsAction $list)
    {
        $result = $list->handle($this->search, $this->getPage());

        return view('livewire.platform.audit', [
            'logs' => $result['rows'],
            'summaries' => $result['summaries'],
        ]);
    }
}

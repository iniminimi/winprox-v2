<?php

namespace App\Livewire\Platform;

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Audit extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_superuser, 403);
    }

    public function render()
    {
        $term = trim($this->search);

        $logs = AuditLog::query()
            ->with(['tenant', 'user'])
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(function ($subQuery) use ($like): void {
                    $subQuery->where('action', 'like', $like)
                        ->orWhere('model_type', 'like', $like)
                        ->orWhereHas('tenant', function ($tenantQuery) use ($like): void {
                            $tenantQuery->where('name', 'like', $like);
                        })
                        ->orWhereHas('user', function ($userQuery) use ($like): void {
                            $userQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->latest('created_at')
            ->limit(100)
            ->get();

        return view('livewire.platform.audit', [
            'logs' => $logs,
        ]);
    }
}

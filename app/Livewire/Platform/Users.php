<?php

namespace App\Livewire\Platform;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Users extends Component
{
    use AuthorizesRequests;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('accessPlatform', User::class);
    }

    public function render()
    {
        $term = trim($this->search);

        $users = User::query()
            ->with('tenant')
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(function ($subQuery) use ($like): void {
                    $subQuery->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('tenant', function ($tenantQuery) use ($like): void {
                            $tenantQuery->where('name', 'like', $like);
                        });
                });
            })
            ->orderByDesc('is_superuser')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return view('livewire.platform.users', [
            'users' => $users,
        ]);
    }
}

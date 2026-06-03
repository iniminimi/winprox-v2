<?php

namespace App\Livewire\Platform;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Users extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_superuser, 403);
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

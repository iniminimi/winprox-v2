<?php

namespace App\Livewire\Platform;

use App\Actions\Platform\SetPlatformUserPasswordAction;
use App\Http\Requests\Platform\SetPlatformUserPasswordRequest;
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

    public ?int $passwordUserId = null;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->authorize('accessPlatform', User::class);
    }

    public function openPassword(int $userId): void
    {
        $target = User::query()->find($userId);
        if ($target === null) {
            return;
        }
        $this->authorize('setPassword', $target);
        $this->passwordUserId = (int) $target->id;
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetErrorBag(['password', 'password_confirmation']);
    }

    public function closePassword(): void
    {
        $this->passwordUserId = null;
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetErrorBag(['password', 'password_confirmation']);
    }

    public function savePassword(SetPlatformUserPasswordAction $setPassword): void
    {
        $target = $this->passwordUserId !== null
            ? User::query()->find($this->passwordUserId)
            : null;
        if ($target === null) {
            $this->closePassword();

            return;
        }
        $this->authorize('setPassword', $target);

        $validated = $this->validate(
            SetPlatformUserPasswordRequest::rulesFor(),
            SetPlatformUserPasswordRequest::messagesFor(),
        );

        $setPassword->handle($target, $validated['password'], (int) auth()->id());
        $name = $target->name;
        $this->closePassword();
        session()->flash('success', __('platform.users.password.saved', ['name' => $name]));
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('livewire.platform.users', [
            'users' => $users,
        ]);
    }
}

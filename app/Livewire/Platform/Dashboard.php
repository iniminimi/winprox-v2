<?php

namespace App\Livewire\Platform;

use App\Models\AuditLog;
use App\Models\HelpChatUnansweredQuestion;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->is_superuser, 403);
    }

    public function render()
    {
        return view('livewire.platform.dashboard', [
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'active_tenants' => Tenant::query()->where('is_active', true)->count(),
                'users' => User::query()->where('is_superuser', false)->count(),
                'issues' => Issue::withoutGlobalScope('tenant')->count(),
                'tasks' => Task::withoutGlobalScope('tenant')->count(),
                'help' => HelpChatUnansweredQuestion::query()->count(),
            ],
            'recentTenants' => Tenant::query()->latest()->take(5)->get(),
            'recentUsers' => User::query()->with('tenant')->latest()->take(5)->get(),
            'recentAuditLogs' => AuditLog::query()->with(['tenant', 'user'])->latest('created_at')->take(8)->get(),
        ]);
    }
}

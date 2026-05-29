<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\ApproveIssueAction;
use App\Models\Issue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    public function approve(int $issue, ApproveIssueAction $approveIssue): void
    {
        $model = Issue::findOrFail($issue);

        $approveIssue->handle($model, auth()->user());
    }

    public function render()
    {
        $issues = Issue::query()
            ->with('location')
            ->latest()
            ->get();

        return view('livewire.issues.index', [
            'issues' => $issues,
        ]);
    }
}

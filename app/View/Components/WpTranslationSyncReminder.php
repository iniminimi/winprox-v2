<?php

namespace App\View\Components;

use App\Actions\Communication\CountPendingIssueTranslationsAction;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class WpTranslationSyncReminder extends Component
{
    public bool $visible = false;

    public int $pendingCount = 0;

    public function __construct(CountPendingIssueTranslationsAction $countPending)
    {
        $user = auth()->user();

        if (
            $user?->is_superuser
            && $user->tenant_id === null
            && ! SupportTenantContext::isActive()
        ) {
            $this->visible = true;
            $this->pendingCount = $countPending->handle();
        }
    }

    public function render(): View
    {
        return view('components.wp-translation-sync-reminder');
    }
}

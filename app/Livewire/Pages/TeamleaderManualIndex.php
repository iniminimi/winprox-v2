<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Livewire\Pages\Concerns\HasManualLocale;
use App\Support\Manual\ManualChapters;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.print')]
#[Title('WinProx Handleiding teamleaders')]
class TeamleaderManualIndex extends Component
{
    use HasManualLocale;

    private const CHAPTER_KEYS = [
        'portal.teamleader.role',
        'portal.teamleader.release',
        'portal.teamleader.workers',
        'portal.teamleader.tasks',
    ];

    public function mount(): void
    {
        $this->mountManualLocale();
    }

    public function changeLocale(string $locale): void
    {
        $this->changeManualLocale($locale, 'manual.teamleaders');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.manual-index', [
            'chapters' => ManualChapters::fromPageHelp(self::CHAPTER_KEYS),
            'generatedAt' => now()->format('d-m-Y'),
            'tenantName' => $this->manualTenantName(),
            'coverPrefix' => 'manual.teamleaders.cover',
            'footerKey' => 'manual.teamleaders.footer',
            'showGettingStarted' => false,
        ]);
    }
}

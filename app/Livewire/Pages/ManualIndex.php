<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Livewire\Pages\Concerns\HasManualLocale;
use App\Support\Manual\ManualChapters;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.print')]
#[Title('WinProx Handleiding')]
class ManualIndex extends Component
{
    use HasManualLocale;

    private const CHAPTER_KEYS = [
        'team',
        'locations.list',
        'locations.show',
        'issues.list',
        'issues.show',
        'issues.create',
        'tasks.list',
        'tasks.show',
        'calendar',
        'dashboard',
        'settings',
        'portal.worker.qr',
        'portal.unit',
        'portal.team',
        'portal.worker.photos',
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
        $this->changeManualLocale($locale, 'manual.general');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.manual-index', [
            'chapters' => ManualChapters::fromPageHelp(self::CHAPTER_KEYS),
            'generatedAt' => now()->format('d-m-Y'),
            'coverPrefix' => 'manual.cover',
            'footerKey' => 'manual.footer',
            'showGettingStarted' => true,
        ]);
    }
}

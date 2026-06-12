<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Livewire\Pages\Concerns\HasManualLocale;
use App\Support\Manual\ManualChapters;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.print')]
#[Title('WinProx Handleiding uitvoerders')]
class WorkerManualIndex extends Component
{
    use HasManualLocale;

    private const CHAPTER_KEYS = [
        'portal.worker.qr',
        'portal.team',
        'portal.unit',
        'portal.worker.photos',
    ];

    public function mount(): void
    {
        $this->mountManualLocale();
    }

    public function changeLocale(string $locale): void
    {
        $this->changeManualLocale($locale, 'manual.workers');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.pages.manual-index', [
            'chapters' => ManualChapters::fromPageHelp(self::CHAPTER_KEYS),
            'generatedAt' => now()->format('d-m-Y'),
            'coverPrefix' => 'manual.workers.cover',
            'footerKey' => 'manual.workers.footer',
            'showGettingStarted' => false,
        ]);
    }
}

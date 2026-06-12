<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Livewire\Pages\Concerns\HasManualLocale;
use App\Support\Manual\StructuredManual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.print')]
#[Title('WinProx Handleiding uitvoerders')]
class WorkerManualIndex extends Component
{
    use HasManualLocale;

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
            'chapters' => StructuredManual::chapters('worker-manual'),
            'generatedAt' => now()->format('d-m-Y'),
            'coverPrefix' => 'worker-manual.cover',
            'footerKey' => 'worker-manual.footer',
            'showGettingStarted' => false,
        ]);
    }
}

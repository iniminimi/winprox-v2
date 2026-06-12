<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Livewire\Pages\Concerns\HasManualLocale;
use App\Support\Manual\StructuredManual;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.print')]
#[Title('WinProx Handleiding teamleaders')]
class TeamleaderManualIndex extends Component
{
    use HasManualLocale;

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
            'chapters' => StructuredManual::chapters('teamleader-manual'),
            'generatedAt' => now()->format('d-m-Y'),
            'coverPrefix' => 'teamleader-manual.cover',
            'footerKey' => 'teamleader-manual.footer',
            'showGettingStarted' => false,
        ]);
    }
}

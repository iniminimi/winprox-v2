<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Livewire\Pages\Concerns\HasManualLocale;
use App\Support\PageHelp;
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
        $chapters = [];

        foreach (self::CHAPTER_KEYS as $key) {
            $data = PageHelp::for($key);

            if ($data !== null) {
                $data['title'] = preg_replace('/^[^\x{2014}]+\x{2014} /u', '', $data['title']);
                $chapters[] = array_merge(['key' => $key], $data);
            }
        }

        return view('livewire.pages.manual-index', [
            'chapters' => $chapters,
            'generatedAt' => now()->format('d-m-Y'),
            'coverPrefix' => 'manual.cover',
            'footerKey' => 'manual.footer',
            'showGettingStarted' => true,
        ]);
    }
}

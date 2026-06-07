<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Support\PageHelp;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.print')]
#[Title('WinProx Handleiding')]
class ManualIndex extends Component
{
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

    public array $availableLocales = ['nl', 'fr', 'en', 'de'];

    #[Url(keep: true)]
    public string $lang = '';

    public function mount(): void
    {
        if ($this->lang !== '' && in_array($this->lang, $this->availableLocales, true)) {
            App::setLocale($this->lang);
        } else {
            $this->lang = App::getLocale();
        }
    }

    public function changeLocale(string $locale): void
    {
        if (! in_array($locale, $this->availableLocales, true)) {
            return;
        }

        $this->redirect(route('manual.index', ['lang' => $locale]), navigate: false);
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
        ]);
    }
}

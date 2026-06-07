<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Support\PageHelp;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
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

    public function render(): \Illuminate\View\View
    {
        $chapters = [];

        foreach (self::CHAPTER_KEYS as $key) {
            $data = PageHelp::for($key);

            if ($data !== null) {
                $data['title'] = Str::replaceFirst('Hulp — ', '', $data['title']);
                $chapters[] = array_merge(['key' => $key], $data);
            }
        }

        return view('livewire.pages.manual-index', [
            'chapters' => $chapters,
            'generatedAt' => now()->format('d-m-Y'),
        ]);
    }
}

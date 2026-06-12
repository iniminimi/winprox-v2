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

    private const ADMIN_CHAPTER_KEYS = [
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

    private const INTERNET_PORTAL_CHAPTER_KEYS = [
        'portal.worker.qr',
        'portal.unit',
        'portal.team',
        'portal.worker.photos',
        'portal.teamleader.role',
        'portal.teamleader.release',
        'portal.teamleader.workers',
        'portal.teamleader.tasks',
    ];

    /** @var list<string> */
    private const CHAPTER_KEYS = [
        ...self::ADMIN_CHAPTER_KEYS,
        ...self::INTERNET_PORTAL_CHAPTER_KEYS,
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
        $chapters = ManualChapters::fromPageHelp(self::CHAPTER_KEYS);
        $splitAt = count(self::ADMIN_CHAPTER_KEYS);

        return view('livewire.pages.manual-index', [
            'chapters' => $chapters,
            'tocSections' => [
                [
                    'id' => 'admin-portal',
                    'label' => __('manual.toc.admin_portal'),
                    'title' => __('manual.sections.admin_portal.title'),
                    'intro' => __('manual.sections.admin_portal.intro'),
                    'chapters' => array_slice($chapters, 0, $splitAt),
                ],
                [
                    'id' => 'internet-portal',
                    'label' => __('manual.toc.internet_portal'),
                    'title' => __('manual.sections.internet_portal.title'),
                    'intro' => __('manual.sections.internet_portal.intro'),
                    'chapters' => array_slice($chapters, $splitAt),
                ],
            ],
            'generatedAt' => now()->format('d-m-Y'),
            'tenantName' => $this->manualTenantName(),
            'coverPrefix' => 'manual.cover',
            'footerKey' => 'manual.footer',
            'showGettingStarted' => true,
        ]);
    }
}

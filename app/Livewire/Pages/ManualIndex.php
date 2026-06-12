<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Livewire\Pages\Concerns\HasManualLocale;
use App\Support\Manual\ManualChapterIcons;
use App\Support\Manual\ManualChapters;
use App\Support\Manual\ManualPortalStatuses;
use App\Support\Manual\ManualScreenshotAssets;
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

    /**
     * @param  list<array<string, mixed>>  $chapters
     * @return list<array<string, mixed>>
     */
    private function chaptersWithScreenshotUrls(array $chapters): array
    {
        return array_map(
            fn (array $chapter): array => [
                ...$chapter,
                'manualScreenshotUrl' => ManualScreenshotAssets::publicUrl($chapter['key'], $this->lang),
            ],
            $chapters,
        );
    }

    public function render(): \Illuminate\View\View
    {
        $chapters = $this->chaptersWithScreenshotUrls(
            ManualChapterIcons::applyToChapters(
                ManualChapters::fromPageHelp(self::CHAPTER_KEYS, withoutStatuses: true),
            ),
        );
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
                    'statusBlock' => ManualPortalStatuses::block('admin_portal'),
                ],
                [
                    'id' => 'internet-portal',
                    'label' => __('manual.toc.internet_portal'),
                    'title' => __('manual.sections.internet_portal.title'),
                    'intro' => __('manual.sections.internet_portal.intro'),
                    'chapters' => array_slice($chapters, $splitAt),
                    'statusBlock' => ManualPortalStatuses::block('internet_portal'),
                ],
            ],
            'generatedAt' => now()->format('d-m-Y'),
            'tenantName' => $this->manualTenantName(),
            'tenantLogoUrl' => $this->manualTenantLogoUrl(),
            'showTenantNameOnCover' => true,
            'coverPrefix' => 'manual.cover',
            'showGettingStarted' => true,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Livewire\Pages\Concerns\HasManualLocale;
use App\Support\Manual\ManualChapterIcons;
use App\Support\Manual\ManualChapters;
use App\Support\Manual\ManualScreenshotAssets;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.print')]
#[Title('WinProx Checkmate handleiding')]
class CheckmateManualIndex extends Component
{
    use HasManualLocale;

    private const ADMIN_CHAPTER_KEYS = [
        'checkmate.dashboard',
        'checkmate.customers',
        'checkmate.workers',
        'checkmate.clock_points',
        'checkmate.presence',
        'checkmate.shifts',
        'checkmate.ciao',
        'checkmate.settings',
        'checkmate.subscription',
    ];

    private const PORTAL_CHAPTER_KEYS = [
        'checkmate.portal.signin',
        'checkmate.portal.day',
        'checkmate.portal.visit',
        'checkmate.portal.hours',
    ];

    public function mount(): void
    {
        $this->mountManualLocale();

        if (! $this->manualIsCheckmate()) {
            $this->redirect(route('manual.hub'), navigate: false);
        }
    }

    public function changeLocale(string $locale): void
    {
        $this->changeManualLocale($locale, 'manual.checkmate');
    }

    /**
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function chaptersFor(array $keys): array
    {
        return array_map(
            fn (array $chapter): array => ManualScreenshotAssets::enrichChapter($chapter, $this->lang),
            ManualChapterIcons::applyToChapters(ManualChapters::fromPageHelp($keys)),
        );
    }

    public function render(): \Illuminate\View\View
    {
        $adminChapters = $this->chaptersFor(self::ADMIN_CHAPTER_KEYS);
        $portalChapters = $this->chaptersFor(self::PORTAL_CHAPTER_KEYS);

        return view('livewire.pages.manual-index', [
            'chapters' => [...$adminChapters, ...$portalChapters],
            'tocSections' => [
                [
                    'id' => 'checkmate-admin',
                    'label' => __('manual.checkmate.toc.admin'),
                    'title' => __('manual.checkmate.sections.admin.title'),
                    'intro' => __('manual.checkmate.sections.admin.intro'),
                    'chapters' => $adminChapters,
                ],
                [
                    'id' => 'checkmate-portal',
                    'label' => __('manual.checkmate.toc.portal'),
                    'title' => __('manual.checkmate.sections.portal.title'),
                    'intro' => __('manual.checkmate.sections.portal.intro'),
                    'chapters' => $portalChapters,
                ],
            ],
            'generatedAt' => now()->format('d-m-Y'),
            'tenantName' => $this->manualTenantName(),
            'tenantLogoUrl' => $this->manualTenantLogoUrl(),
            'showTenantNameOnCover' => true,
            'coverPrefix' => 'manual.checkmate.cover',
            'showGettingStarted' => false,
            'brandLogoUrl' => asset('images/landing/work_on_location/winprox_checkmate.png'),
            'brandLogoAlt' => 'WinProx Checkmate',
        ]);
    }
}

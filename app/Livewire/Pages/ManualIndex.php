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
            fn (array $chapter): array => ManualScreenshotAssets::enrichChapter($chapter, $this->lang),
            $chapters,
        );
    }

    /**
     * @param  array<string, mixed>  $chapter
     * @return array<string, mixed>
     */
    private function enrichStatusChapter(array $chapter): array
    {
        $enriched = ManualChapterIcons::applyToChapters([$chapter])[0];

        return ManualScreenshotAssets::enrichChapter($enriched, $this->lang);
    }

    public function render(): \Illuminate\View\View
    {
        $pageHelpKeys = ManualChapters::pageHelpKeys();
        $helpChapters = $this->chaptersWithScreenshotUrls(
            ManualChapterIcons::applyToChapters(
                ManualChapters::fromPageHelp($pageHelpKeys, withoutStatuses: true),
            ),
        );
        $splitAt = ManualChapters::adminPageHelpKeyCount();

        $adminChapters = array_slice($helpChapters, 0, $splitAt);
        $internetChapters = array_slice($helpChapters, $splitAt);

        if ($adminStatusChapter = ManualPortalStatuses::asChapter('admin_portal')) {
            $adminChapters[] = $this->enrichStatusChapter($adminStatusChapter);
        }

        if ($internetStatusChapter = ManualPortalStatuses::asChapter('internet_portal')) {
            $internetChapters[] = $this->enrichStatusChapter($internetStatusChapter);
        }

        $chapters = [...$adminChapters, ...$internetChapters];

        return view('livewire.pages.manual-index', [
            'chapters' => $chapters,
            'tocSections' => [
                [
                    'id' => 'admin-portal',
                    'label' => __('manual.toc.admin_portal'),
                    'title' => __('manual.sections.admin_portal.title'),
                    'intro' => __('manual.sections.admin_portal.intro'),
                    'chapters' => $adminChapters,
                ],
                [
                    'id' => 'internet-portal',
                    'label' => __('manual.toc.internet_portal'),
                    'title' => __('manual.sections.internet_portal.title'),
                    'intro' => __('manual.sections.internet_portal.intro'),
                    'chapters' => $internetChapters,
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

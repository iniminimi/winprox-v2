<?php

namespace App\Console\Commands;

use App\Actions\Communication\RunPendingAnnouncementTranslationsAction;
use App\Actions\Communication\RunPendingCategoryTranslationsAction;
use App\Actions\Communication\RunPendingDocumentTranslationsAction;
use App\Actions\Communication\RunPendingEsgIndicatorTranslationsAction;
use App\Actions\Communication\RunPendingInternalTeamTranslationsAction;
use App\Actions\Communication\RunPendingIssueTranslationsAction;
use App\Actions\Communication\RunPendingTaskTranslationsAction;
use App\Actions\Communication\RunPendingUnitTranslationsAction;
use App\Enums\AnnouncementTranslationStatus;
use App\Enums\CategoryTranslationStatus;
use App\Enums\DocumentTranslationStatus;
use App\Enums\EsgIndicatorTranslationStatus;
use App\Enums\InternalTeamTranslationStatus;
use App\Enums\IssueTranslationStatus;
use App\Enums\TaskTranslationStatus;
use App\Enums\UnitTranslationStatus;
use App\Models\AnnouncementTranslation;
use App\Models\CategoryTranslation;
use App\Models\DocumentTranslation;
use App\Models\EsgIndicatorTranslation;
use App\Models\InternalTeamTranslation;
use App\Models\IssueTranslation;
use App\Models\TaskTranslation;
use App\Models\UnitTranslation;
use Illuminate\Console\Command;

class TranslateLocalAllCommand extends Command
{
    protected $signature = 'winprox:translate-local-all
                            {--limit= : Max pending translations per type per round}
                            {--all : Keep processing until no pending translations remain}
                            {--progress : Show live progress while processing translation types}';

    protected $description = 'Translate all pending records locally without translation export/import sync';

    public function handle(
        RunPendingIssueTranslationsAction $runIssues,
        RunPendingTaskTranslationsAction $runTasks,
        RunPendingAnnouncementTranslationsAction $runAnnouncements,
        RunPendingDocumentTranslationsAction $runDocuments,
        RunPendingUnitTranslationsAction $runUnits,
        RunPendingEsgIndicatorTranslationsAction $runEsgIndicators,
        RunPendingCategoryTranslationsAction $runCategories,
        RunPendingInternalTeamTranslationsAction $runTeams,
    ): int {
        $processAll = (bool) $this->option('all');
        $limit = $this->option('limit');
        $parsedLimit = $limit !== null ? (int) $limit : null;
        $showProgress = (bool) $this->option('progress');

        if ($processAll && $parsedLimit !== null) {
            $this->warn('Ignoring --limit because --all is set.');
            $parsedLimit = null;
        }

        $this->line('Starting local translation run...');
        $this->line('Database: '.config('database.default').' / '.config('database.connections.'.config('database.default').'.database'));

        $totals = [
            'issues' => 0,
            'tasks' => 0,
            'announcements' => 0,
            'documents' => 0,
            'units' => 0,
            'esg_indicators' => 0,
            'categories' => 0,
            'teams' => 0,
        ];

        do {
            $pendingBefore = $this->pendingCounts();
            $roundTotal = array_sum($pendingBefore);

            if ($roundTotal === 0) {
                break;
            }

            if ($processAll || $showProgress) {
                $this->newLine();
                $this->line('Pending before this round:');
                $this->printPendingCounts($pendingBefore);
            }

            $progressBar = null;
            if ($showProgress) {
                $progressBar = $this->output->createProgressBar(8);
                $progressBar->start();
            }

            $issueCount = $runIssues->handle($parsedLimit, null, $showProgress ? $this->progressLogger('Issues') : null);
            $progressBar?->advance();
            $taskCount = $runTasks->handle($parsedLimit, null, $showProgress ? $this->progressLogger('Tasks') : null);
            $progressBar?->advance();
            $announcementCount = $runAnnouncements->handle($parsedLimit, null, $showProgress ? $this->progressLogger('Announcements') : null);
            $progressBar?->advance();
            $documentCount = $runDocuments->handle($parsedLimit, null, $showProgress ? $this->progressLogger('Documents') : null);
            $progressBar?->advance();
            $unitCount = $runUnits->handle($parsedLimit, null, $showProgress ? $this->progressLogger('Units') : null);
            $progressBar?->advance();
            $esgIndicatorCount = $runEsgIndicators->handle($parsedLimit, null, $showProgress ? $this->progressLogger('ESG indicators') : null);
            $progressBar?->advance();
            $categoryCount = $runCategories->handle($parsedLimit, null, $showProgress ? $this->progressLogger('Categories') : null);
            $progressBar?->advance();
            $teamCount = $runTeams->handle($parsedLimit, null, $showProgress ? $this->progressLogger('Teams') : null);
            $progressBar?->advance();

            if ($progressBar !== null) {
                $progressBar->finish();
                $this->newLine(2);
            }

            $totals['issues'] += $issueCount;
            $totals['tasks'] += $taskCount;
            $totals['announcements'] += $announcementCount;
            $totals['documents'] += $documentCount;
            $totals['units'] += $unitCount;
            $totals['esg_indicators'] += $esgIndicatorCount;
            $totals['categories'] += $categoryCount;
            $totals['teams'] += $teamCount;

            $processedThisRound = $issueCount + $taskCount + $announcementCount + $documentCount + $unitCount + $esgIndicatorCount + $categoryCount + $teamCount;

            if (! $processAll) {
                break;
            }

            if ($processedThisRound === 0) {
                $this->warn('No translations processed in this round; stopping to avoid an endless loop.');

                break;
            }
        } while ($processAll);

        $pendingAfter = $this->pendingCounts();
        $processedTotal = array_sum($totals);

        $this->newLine();
        $this->line('Processed this run:');
        $this->line("Issues: {$totals['issues']}");
        $this->line("Tasks: {$totals['tasks']}");
        $this->line("Announcements: {$totals['announcements']}");
        $this->line("Documents: {$totals['documents']}");
        $this->line("Units: {$totals['units']}");
        $this->line("ESG indicators: {$totals['esg_indicators']}");
        $this->line("Categories: {$totals['categories']}");
        $this->line("Teams: {$totals['teams']}");
        $this->newLine();
        $this->line('Still pending:');
        $this->printPendingCounts($pendingAfter);

        if ($processedTotal === 0 && array_sum($pendingAfter) === 0) {
            $this->info('Nothing pending. Local translation database is up to date.');
        } elseif (array_sum($pendingAfter) > 0) {
            $this->warn('Some translations are still pending. Run again with --all to process the rest.');
        } else {
            $this->info("Local translation run complete. Total processed: {$processedTotal}.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{issues: int, tasks: int, announcements: int, documents: int, units: int, esg_indicators: int, categories: int, teams: int}
     */
    private function pendingCounts(): array
    {
        return [
            'issues' => IssueTranslation::query()
                ->where('status', IssueTranslationStatus::Pending)
                ->whereHas('issue', fn ($query) => $query->whereNotNull('approved_at'))
                ->count(),
            'tasks' => TaskTranslation::query()
                ->where('status', TaskTranslationStatus::Pending)
                ->whereHas('task', fn ($query) => $query->whereNotNull('description')->where('description', '!=', ''))
                ->count(),
            'announcements' => AnnouncementTranslation::query()
                ->where('status', AnnouncementTranslationStatus::Pending)
                ->whereHas('announcement', fn ($query) => $query->where('is_active', true))
                ->count(),
            'documents' => DocumentTranslation::query()
                ->where('status', DocumentTranslationStatus::Pending)
                ->whereHas('document', fn ($query) => $query->where('is_active', true))
                ->count(),
            'units' => UnitTranslation::query()
                ->where('status', UnitTranslationStatus::Pending)
                ->whereHas('unit', fn ($query) => $query->where('is_active', true))
                ->count(),
            'esg_indicators' => EsgIndicatorTranslation::query()
                ->where('status', EsgIndicatorTranslationStatus::Pending)
                ->whereHas('indicator', fn ($query) => $query->where('is_active', true))
                ->count(),
            'categories' => CategoryTranslation::query()
                ->where('status', CategoryTranslationStatus::Pending)
                ->whereHas('category', fn ($query) => $query->where('name', '!=', ''))
                ->count(),
            'teams' => InternalTeamTranslation::query()
                ->where('status', InternalTeamTranslationStatus::Pending)
                ->whereHas('team', fn ($query) => $query->where('is_active', true)->where('name', '!=', ''))
                ->count(),
        ];
    }

    /**
     * @param  array{issues: int, tasks: int, announcements: int, documents: int, units: int, esg_indicators: int, categories: int, teams: int}  $counts
     */
    private function printPendingCounts(array $counts): void
    {
        $this->line("Issues: {$counts['issues']}");
        $this->line("Tasks: {$counts['tasks']}");
        $this->line("Announcements: {$counts['announcements']}");
        $this->line("Documents: {$counts['documents']}");
        $this->line("Units: {$counts['units']}");
        $this->line("ESG indicators: {$counts['esg_indicators']}");
        $this->line("Categories: {$counts['categories']}");
        $this->line("Teams: {$counts['teams']}");
        $this->line('Total: '.array_sum($counts));
    }

    private function progressLogger(string $label): callable
    {
        return function (int $done, int $total) use ($label): void {
            $this->line("{$label}: {$done}/{$total}");
        };
    }
}

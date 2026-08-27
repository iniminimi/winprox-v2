<?php

namespace App\Actions\Communication;

use App\Enums\AnnouncementTranslationStatus;
use App\Enums\CategoryTranslationStatus;
use App\Enums\DocumentTranslationStatus;
use App\Enums\EsgIndicatorTranslationStatus;
use App\Enums\InternalTeamTranslationStatus;
use App\Enums\IssueTranslationStatus;
use App\Enums\LocationTranslationStatus;
use App\Enums\TaskTranslationStatus;
use App\Enums\UnitCheckListTranslationStatus;
use App\Enums\UnitTranslationStatus;
use App\Models\Announcement;
use App\Models\AnnouncementTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Document;
use App\Models\DocumentTranslation;
use App\Models\EsgIndicator;
use App\Models\EsgIndicatorTranslation;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Models\Issue;
use App\Models\IssueTranslation;
use App\Models\Location;
use App\Models\LocationTranslation;
use App\Models\Task;
use App\Models\TaskTranslation;
use App\Models\Unit;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListTranslation;
use App\Models\UnitTranslation;
use App\Support\Translation\TranslationOutputGuard;
use Illuminate\Database\Eloquent\Model;

/**
 * Marks completed content translations that contain model meta-replies as pending
 * so the next local translation run can replace them.
 */
class InvalidateUnusableContentTranslationsAction
{
    /**
     * @return array{invalidated: int}
     */
    public function handle(): array
    {
        $invalidated = 0;

        $invalidated += $this->invalidateSimple(
            IssueTranslation::class,
            'issue',
            IssueTranslationStatus::Completed,
            IssueTranslationStatus::Pending,
            ['description'],
            fn (Issue $parent): array => ['description' => (string) $parent->description],
        );

        $invalidated += $this->invalidateSimple(
            TaskTranslation::class,
            'task',
            TaskTranslationStatus::Completed,
            TaskTranslationStatus::Pending,
            ['description'],
            fn (Task $parent): array => ['description' => (string) ($parent->description ?? '')],
        );

        $invalidated += $this->invalidateSimple(
            AnnouncementTranslation::class,
            'announcement',
            AnnouncementTranslationStatus::Completed,
            AnnouncementTranslationStatus::Pending,
            ['description'],
            fn (Announcement $parent): array => ['description' => (string) $parent->description],
        );

        $invalidated += $this->invalidateSimple(
            DocumentTranslation::class,
            'document',
            DocumentTranslationStatus::Completed,
            DocumentTranslationStatus::Pending,
            ['description'],
            fn (Document $parent): array => ['description' => (string) $parent->description],
        );

        $invalidated += $this->invalidateSimple(
            UnitTranslation::class,
            'unit',
            UnitTranslationStatus::Completed,
            UnitTranslationStatus::Pending,
            ['name', 'description'],
            fn (Unit $parent): array => [
                'name' => (string) $parent->name,
                'description' => (string) ($parent->description ?? ''),
            ],
        );

        $invalidated += $this->invalidateSimple(
            LocationTranslation::class,
            'location',
            LocationTranslationStatus::Completed,
            LocationTranslationStatus::Pending,
            ['name'],
            fn (Location $parent): array => ['name' => (string) $parent->name],
        );

        $invalidated += $this->invalidateSimple(
            CategoryTranslation::class,
            'category',
            CategoryTranslationStatus::Completed,
            CategoryTranslationStatus::Pending,
            ['name'],
            fn (Category $parent): array => ['name' => (string) $parent->name],
        );

        $invalidated += $this->invalidateSimple(
            InternalTeamTranslation::class,
            'team',
            InternalTeamTranslationStatus::Completed,
            InternalTeamTranslationStatus::Pending,
            ['name'],
            fn (InternalTeam $parent): array => ['name' => (string) $parent->name],
        );

        $invalidated += $this->invalidateSimple(
            EsgIndicatorTranslation::class,
            'indicator',
            EsgIndicatorTranslationStatus::Completed,
            EsgIndicatorTranslationStatus::Pending,
            ['name'],
            fn (EsgIndicator $parent): array => ['name' => (string) $parent->name],
        );

        $invalidated += $this->invalidateCheckLists();

        return ['invalidated' => $invalidated];
    }

    /**
     * @param  class-string<Model>  $translationClass
     * @param  list<string>  $fields
     * @param  callable(Model): array<string, string>  $sourcesFor
     */
    private function invalidateSimple(
        string $translationClass,
        string $relation,
        mixed $completed,
        mixed $pending,
        array $fields,
        callable $sourcesFor,
    ): int {
        $count = 0;

        $translationClass::query()
            ->where('status', $completed)
            ->with($relation)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count, $relation, $fields, $sourcesFor, $pending): void {
                foreach ($rows as $row) {
                    $parent = $row->{$relation};
                    if (! $parent instanceof Model) {
                        continue;
                    }

                    /** @var array<string, string> $sources */
                    $sources = $sourcesFor($parent);
                    $unusable = false;
                    $cleared = [];

                    foreach ($fields as $field) {
                        $value = trim((string) ($row->{$field} ?? ''));
                        $cleared[$field] = null;
                        if ($value !== '' && TranslationOutputGuard::isUnusable($value, $sources[$field] ?? null)) {
                            $unusable = true;
                        }
                    }

                    if (! $unusable) {
                        continue;
                    }

                    $row->fill($cleared + ['status' => $pending])->save();
                    $count++;
                }
            });

        return $count;
    }

    private function invalidateCheckLists(): int
    {
        $count = 0;

        UnitCheckListTranslation::query()
            ->where('status', UnitCheckListTranslationStatus::Completed)
            ->with('list')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    $list = $row->list;
                    if (! $list instanceof UnitCheckList) {
                        continue;
                    }

                    $name = trim((string) ($row->name ?? ''));
                    $items = is_array($row->items) ? $row->items : [];
                    $sourceItems = $list->sourceItemLabels();
                    $bad = $name !== '' && TranslationOutputGuard::isUnusable($name, (string) $list->name);

                    foreach ($items as $index => $label) {
                        $source = (string) ($sourceItems[$index] ?? '');
                        if (TranslationOutputGuard::isUnusable((string) $label, $source)) {
                            $bad = true;
                            break;
                        }
                    }

                    if (! $bad) {
                        continue;
                    }

                    $row->fill([
                        'name' => null,
                        'items' => null,
                        'status' => UnitCheckListTranslationStatus::Pending,
                    ])->save();
                    $count++;
                }
            });

        return $count;
    }
}

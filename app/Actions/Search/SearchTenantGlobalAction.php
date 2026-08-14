<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Faq\FaqSearchIndex;
use App\Support\Search\GlobalSearchQuery;
use App\Support\Search\GlobalSearchTerms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

final class SearchTenantGlobalAction
{
    private const RESULT_LIMIT = 5;

    /**
     * @return Collection<string, Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>>
     */
    public function handle(User $actor, int $tenantId, string $query): Collection
    {
        $terms = GlobalSearchTerms::fromQuery($query);
        if ($terms === []) {
            return collect();
        }

        $results = collect();

        if ($actor->can('viewAny', Location::class)) {
            $locations = $this->searchLocations($tenantId, $terms);
            if ($locations->isNotEmpty()) {
                $results['locations'] = $locations;
            }
        }

        if ($actor->can('viewAny', Unit::class)) {
            $units = $this->searchUnits($tenantId, $terms, $query);
            if ($units->isNotEmpty()) {
                $results['units'] = $units;
            }
        }

        if ($actor->can('viewAny', Worker::class)) {
            $workers = $this->searchWorkers($tenantId, $terms);
            if ($workers->isNotEmpty()) {
                $results['workers'] = $workers;
            }
        }

        if ($actor->can('viewAny', User::class)) {
            $colleagues = $this->searchColleagues($tenantId, $terms);
            if ($colleagues->isNotEmpty()) {
                $results['colleagues'] = $colleagues;
            }
        }

        if ($actor->can('viewAny', InternalTeam::class)) {
            $teams = $this->searchTeams($tenantId, $terms);
            if ($teams->isNotEmpty()) {
                $results['teams'] = $teams;
            }
        }

        if ($actor->can('viewAny', Issue::class)) {
            $issues = $this->searchIssues($tenantId, $terms);
            if ($issues->isNotEmpty()) {
                $results['issues'] = $issues;
            }
        }

        if ($actor->can('viewAny', Task::class)) {
            $tasks = $this->searchTasks($tenantId, $terms);
            if ($tasks->isNotEmpty()) {
                $results['tasks'] = $tasks;
            }
        }

        $pages = $this->searchPages($terms);
        if ($pages->isNotEmpty()) {
            $results['pages'] = $pages;
        }

        $faq = $this->searchFaq($terms);
        if ($faq->isNotEmpty()) {
            $results['faq'] = $faq;
        }

        return $results;
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchLocations(int $tenantId, array $terms): Collection
    {
        $query = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        GlobalSearchQuery::applyAllTerms($query, $terms, static function (Builder $termQuery, string $term): void {
            GlobalSearchQuery::applyColumnLike($termQuery, $term, [
                'name',
                'address',
                'street',
                'house_number',
                'postal_code',
                'city',
                'notes',
            ]);
        });

        return $query
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(static fn (Location $location): array => [
                'id' => $location->id,
                'type' => 'location',
                'title' => $location->localizedName(),
                'subtitle' => $location->formattedAddress(),
                'url' => route('locations.show', ['location' => $location->id]),
            ]);
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchUnits(int $tenantId, array $terms, string $rawQuery): Collection
    {
        $query = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('location:id,name');

        GlobalSearchQuery::applyAllTerms($query, $terms, static function (Builder $termQuery, string $term): void {
            GlobalSearchQuery::applyColumnLike($termQuery, $term, ['name', 'description']);
        });

        return $query
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(static function (Unit $unit) use ($rawQuery): array {
                $subtitle = (string) ($unit->location?->localizedName() ?? '');
                if (trim((string) $unit->description) !== '') {
                    $subtitle = $subtitle !== ''
                        ? $subtitle.' · '.$unit->description
                        : (string) $unit->description;
                }

                return [
                    'id' => $unit->id,
                    'type' => 'unit',
                    'title' => (string) $unit->name,
                    'subtitle' => $subtitle,
                    'url' => $unit->location_id
                        ? route('locations.show', [
                            'location' => $unit->location_id,
                            'unit' => trim($rawQuery),
                        ])
                        : route('locations.index', ['q' => trim($rawQuery)]),
                ];
            });
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchWorkers(int $tenantId, array $terms): Collection
    {
        $query = Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['team' => fn ($q) => $q->select('id', 'name', 'original_language')->with('translations')]);

        GlobalSearchQuery::applyAllTerms($query, $terms, static function (Builder $termQuery, string $term): void {
            GlobalSearchQuery::applyWorkerNameMatch($termQuery, $term);
        });

        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(static fn (Worker $worker): array => [
                'id' => $worker->id,
                'type' => 'worker',
                'title' => $worker->displayName(),
                'subtitle' => (string) ($worker->team?->localizedName() ?? ''),
                'url' => route('team.index', ['section' => 'teams', 'worker' => $worker->id]),
            ]);
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchColleagues(int $tenantId, array $terms): Collection
    {
        $query = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_superuser', false)
            ->where('is_active', true);

        GlobalSearchQuery::applyAllTerms($query, $terms, static function (Builder $termQuery, string $term): void {
            GlobalSearchQuery::applyColumnLike($termQuery, $term, ['name', 'email']);
        });

        return $query
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'type' => 'colleague',
                'title' => (string) $user->name,
                'subtitle' => (string) $user->email,
                'url' => route('team.index', ['section' => 'backoffice', 'q' => $user->name]),
            ]);
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchTeams(int $tenantId, array $terms): Collection
    {
        $query = InternalTeam::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('translations');

        GlobalSearchQuery::applyAllTerms($query, $terms, static function (Builder $termQuery, string $term): void {
            GlobalSearchQuery::applyColumnLike($termQuery, $term, ['name']);
        });

        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(static fn (InternalTeam $team): array => [
                'id' => $team->id,
                'type' => 'team',
                'title' => $team->localizedName(),
                'subtitle' => '',
                'url' => route('team.index', ['section' => 'teams', 'team' => $team->id]),
            ]);
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchIssues(int $tenantId, array $terms): Collection
    {
        $query = Issue::query()
            ->where('tenant_id', $tenantId)
            ->with(['location:id,name', 'unit:id,name', 'translations']);

        GlobalSearchQuery::applyAllTerms($query, $terms, static function (Builder $termQuery, string $term): void {
            $termQuery->where(static function (Builder $issueQuery) use ($term): void {
                GlobalSearchQuery::applyColumnLike($issueQuery, $term, [
                    'description',
                    'reporter_name',
                    'reporter_contact',
                ]);

                if (ctype_digit($term)) {
                    $issueQuery->orWhere('id', (int) $term);
                }

                $issueQuery->orWhereHas('location', static function (Builder $locationQuery) use ($term): void {
                    GlobalSearchQuery::applyColumnLike($locationQuery, $term, [
                        'name',
                        'street',
                        'city',
                        'postal_code',
                        'address',
                    ]);
                });
                $issueQuery->orWhereHas('unit', static function (Builder $unitQuery) use ($term): void {
                    GlobalSearchQuery::applyColumnLike($unitQuery, $term, ['name', 'description']);
                });
            });
        });

        return $query
            ->latest()
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(static fn (Issue $issue): array => [
                'id' => $issue->id,
                'type' => 'issue',
                'title' => '#'.$issue->id.' - '.mb_strimwidth($issue->localizedDescription(), 0, 50, '...'),
                'subtitle' => trim((string) ($issue->location?->localizedName() ?? '').($issue->unit ? ' · '.$issue->unit->localizedName() : '')),
                'url' => route('issues.show', ['issue' => $issue->id]),
            ]);
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: int|string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchTasks(int $tenantId, array $terms): Collection
    {
        $query = Task::query()
            ->forApprovedIssue()
            ->where('tenant_id', $tenantId)
            ->with(['issue.location', 'issue.unit', 'team' => fn ($q) => $q->select('id', 'name', 'original_language')->with('translations')]);

        GlobalSearchQuery::applyAllTerms($query, $terms, static function (Builder $termQuery, string $term): void {
            $termQuery->where(static function (Builder $taskQuery) use ($term): void {
                GlobalSearchQuery::applyColumnLike($taskQuery, $term, ['description']);

                if (ctype_digit($term)) {
                    $taskQuery->orWhere('id', (int) $term);
                }

                $taskQuery->orWhereHas('issue', static function (Builder $issueQuery) use ($term): void {
                    GlobalSearchQuery::applyColumnLike($issueQuery, $term, [
                        'description',
                        'reporter_name',
                    ]);
                    if (ctype_digit($term)) {
                        $issueQuery->orWhere('id', (int) $term);
                    }
                });
                $taskQuery->orWhereHas('team', static function (Builder $teamQuery) use ($term): void {
                    GlobalSearchQuery::applyColumnLike($teamQuery, $term, ['name']);
                });
            });
        });

        return $query
            ->latest()
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(static fn (Task $task): array => [
                'id' => $task->id,
                'type' => 'task',
                'title' => '#'.$task->id.($task->displayDescription() !== '' ? ' - '.mb_strimwidth($task->displayDescription(), 0, 40, '...') : ''),
                'subtitle' => trim((string) ($task->team?->localizedName() ?? '').($task->issue?->location?->localizedName() ? ' · '.$task->issue->location->localizedName() : '')),
                'url' => route('tasks.show', ['task' => $task->id]),
            ]);
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchFaq(array $terms): Collection
    {
        if (! Route::has('faq.index')) {
            return collect();
        }

        return collect(FaqSearchIndex::matchingItems($terms))
            ->map(static fn (array $item): array => [
                'id' => $item['slug'],
                'type' => 'faq',
                'title' => $item['title'],
                'subtitle' => $item['subtitle'],
                'url' => route('faq.index', ['open' => $item['slug']]),
            ]);
    }

    /**
     * @param  list<string>  $terms
     * @return Collection<int, array{id: string, type: string, title: string, subtitle: string, url: string}>
     */
    private function searchPages(array $terms): Collection
    {
        $pages = [
            ['id' => 'dashboard', 'route' => 'dashboard', 'label' => 'common.nav.dashboard'],
            ['id' => 'locations', 'route' => 'locations.index', 'label' => 'common.nav.locations'],
            ['id' => 'categories', 'route' => 'locations.index', 'params' => ['section' => 'categories'], 'label' => 'locations.categories.title'],
            ['id' => 'issues', 'route' => 'issues.index', 'label' => 'common.nav.issues'],
            ['id' => 'tasks', 'route' => 'tasks.index', 'label' => 'common.nav.tasks'],
            ['id' => 'calendar', 'route' => 'calendar.index', 'label' => 'common.nav.calendar'],
            ['id' => 'backoffice', 'route' => 'team.index', 'params' => ['section' => 'backoffice'], 'label' => 'common.nav.backoffice'],
            ['id' => 'users', 'route' => 'team.index', 'params' => ['section' => 'backoffice'], 'label' => 'common.nav.users'],
            ['id' => 'teams', 'route' => 'team.index', 'params' => ['section' => 'teams'], 'label' => 'team.nav.teams'],
            ['id' => 'settings', 'route' => 'settings.index', 'label' => 'common.nav.settings'],
            ['id' => 'subscription', 'route' => 'subscription.index', 'label' => 'common.nav.subscription'],
            ['id' => 'faq', 'route' => 'faq.index', 'label' => 'common.nav.faq'],
            ['id' => 'legal', 'route' => 'legal.index', 'label' => 'common.nav.legal'],
            ['id' => 'contact', 'route' => 'contact.index', 'label' => 'common.nav.contact'],
        ];

        return collect($pages)
            ->filter(static function (array $page) use ($terms): bool {
                if (! Route::has($page['route'])) {
                    return false;
                }

                $label = mb_strtolower((string) __($page['label']));

                foreach ($terms as $term) {
                    if (str_contains($label, mb_strtolower($term))) {
                        return true;
                    }
                }

                return false;
            })
            ->map(static fn (array $page): array => [
                'id' => $page['id'],
                'type' => 'page',
                'title' => (string) __($page['label']),
                'subtitle' => '',
                'url' => route($page['route'], $page['params'] ?? []),
            ])
            ->values();
    }
}

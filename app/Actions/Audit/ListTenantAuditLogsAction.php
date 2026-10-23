<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Enums\WorkShiftStatus;
use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\Customer;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\User;
use App\Models\Worker;
use App\Support\Audit\DescribeTenantAuditLog;
use App\Support\Audit\SummarizeAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListTenantAuditLogsAction
{
    /**
     * Acties die voor een beheerder geen auditwaarde hebben in dit verslag:
     * puur operationele hartslag (elke prik staat al leesbaar onder Uren).
     * Enkel expliciet beoordeelde codes — geen brede prefixes.
     *
     * @var list<string>
     */
    public const HIDDEN_ACTIONS = [
        'time.shift.started',       // elke inklok (manueel heeft eigen regel)
        'time.visit.started',       // werkbezoek-hartslag
        'time.visit.ended',         // werkbezoek-hartslag
        'worker.clock_display_punched', // elke schermprik
        'clock_point.display_secret_rotated', // routine-rotatie
    ];

    /**
     * Categorie → actie-selectie. 'exact' wint van prefix (security-codes
     * binnen worker./clock_point.-prefixes horen bij Beveiliging, niet Uitvoerders).
     *
     * @var array<string, array{exact: list<string>, prefix: list<string>}>
     */
    public const CATEGORY_RULES = [
        'security' => [
            'exact' => [
                'worker.clock_device_bound',
                'worker.clock_device_cleared',
                'worker.clock_device_refused',
                'worker.clock_pin_set',
                'worker.clock_pin_cleared',
                'worker.passkey_registered',
                'worker.passkeys_revoked',
                'worker.clock_display_pin_set',
                'worker.clock_display_pin_cleared',
                'worker.clock_display_pin_failed',
                'worker.clock_display_pin_blocked_attempt',
                'worker.icon_reset',
                'clock_point.qr_blocked',
                'auth.email_verified',
            ],
            'prefix' => [],
        ],
        'time' => [
            'exact' => [],
            'prefix' => ['work_shift.', 'planned_shift.', 'time.', 'presence_compliance.'],
        ],
        'workers' => [
            'exact' => [],
            'prefix' => ['worker.', 'workers.', 'internal_team.', 'team.'],
        ],
        'places' => [
            'exact' => [],
            'prefix' => ['customer.', 'customers.', 'location.', 'locations.', 'unit.', 'units.', 'clock_point.', 'category.', 'categories.', 'site.'],
        ],
        'settings' => [
            'exact' => [],
            'prefix' => ['tenant.', 'subscription.', 'api_token.', 'settings.', 'webhook_endpoint.'],
        ],
    ];

    public const CATEGORIES = ['all', 'time', 'workers', 'places', 'settings', 'security', 'other'];

    public function __construct(
        private DescribeTenantAuditLog $describe,
        private SummarizeAuditLog $summarize,
    ) {}

    /**
     * @return array{
     *     rows: LengthAwarePaginator,
     *     summaries: Collection<int, array{title: string, meta: string, context: string|null, action: string}>
     * }
     */
    public function handle(int $tenantId, string $search = '', string $category = 'all', int $page = 1, int $perPage = 50): array
    {
        $search = trim($search);
        $category = in_array($category, self::CATEGORIES, true) ? $category : 'all';

        $rows = AuditLog::query()
            ->with(['user'])
            ->where('tenant_id', $tenantId)
            ->whereNotIn('action', array_merge(ListPlatformAuditLogsAction::HIDDEN_ACTIONS, self::HIDDEN_ACTIONS))
            // time.shift.ended alleen tonen als het een automatische (systeem-)
            // afsluiting betreft; gewone uitkloks en geforceerde afsluitingen
            // hebben hun eigen leesbaardere registraties.
            ->where(function ($q): void {
                $q->where('action', '!=', 'time.shift.ended')
                    ->orWhere(function ($auto): void {
                        $auto->where('action', 'time.shift.ended')
                            ->where('payload->status', WorkShiftStatus::ForceClosed->value)
                            ->whereNull('payload->actor_user_id');
                    });
            })
            ->when($category !== 'all', function ($query) use ($category): void {
                $this->applyCategory($query, $category);
            })
            ->when($search !== '', function ($query) use ($search, $tenantId): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $actionCodes = $this->summarize->actionCodesMatchingLabel($search);

                $query->where(function ($subQuery) use ($like, $actionCodes, $search, $tenantId): void {
                    $subQuery->where('action', 'like', $like)
                        ->orWhere('model_type', 'like', $like)
                        ->orWhereRaw('CAST(model_id AS CHAR) LIKE ?', [$like])
                        ->orWhereHas('user', function ($userQuery) use ($like): void {
                            $userQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });

                    if ($actionCodes !== []) {
                        $subQuery->orWhereIn('action', $actionCodes);
                    }

                    $this->applySubjectSearch($subQuery, $search, $tenantId);
                });
            })
            ->latest('created_at')
            ->paginate(perPage: $perPage, page: $page);

        return [
            'rows' => $rows,
            'summaries' => $this->describe->describeAll($rows->getCollection()),
        ];
    }

    /**
     * Categorie-selectie als WHERE (exact wint van prefix).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AuditLog>  $query
     */
    private function applyCategory($query, string $category): void
    {
        if ($category === 'other') {
            // Alles wat géén exacte match of prefix-match heeft in de regels.
            foreach (self::CATEGORY_RULES as $rule) {
                foreach ($rule['exact'] as $code) {
                    $query->where('action', '!=', $code);
                }
                foreach ($rule['prefix'] as $prefix) {
                    $query->where('action', 'not like', $prefix.'%');
                }
            }

            return;
        }

        // Exacte codes van andere categorieën uitsluiten (bv. security-codes
        // binnen worker.* horen niet bij Uitvoerders). Buiten de OR-groep houden.
        foreach (self::CATEGORY_RULES as $cat => $rule) {
            if ($cat === $category) {
                continue;
            }
            foreach ($rule['exact'] as $code) {
                $query->where('action', '!=', $code);
            }
        }

        $rule = self::CATEGORY_RULES[$category] ?? ['exact' => [], 'prefix' => []];
        $query->where(function ($q) use ($rule): void {
            foreach ($rule['exact'] as $code) {
                $q->orWhere('action', $code);
            }
            foreach ($rule['prefix'] as $prefix) {
                $q->orWhere('action', 'like', $prefix.'%');
            }
        });
    }

    /**
     * Zoekterm ook laten matchen op opgeloste onderwerp-namen: één lookup per
     * onderwerp-type (gebundeld, geen N+1), daarna id-filters op model/payload.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AuditLog>  $query
     */
    private function applySubjectSearch($query, string $search, int $tenantId): void
    {
        $like = '%'.addcslashes($search, '%_\\').'%';

        $workerIds = Worker::query()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
            ->limit(500)->pluck('id');
        $customerIds = Customer::query()->where('tenant_id', $tenantId)
            ->where('name', 'like', $like)->limit(200)->pluck('id');
        $locationIds = Location::query()->where('tenant_id', $tenantId)
            ->where('name', 'like', $like)->limit(200)->pluck('id');
        $teamIds = InternalTeam::query()->where('tenant_id', $tenantId)
            ->where('name', 'like', $like)->limit(200)->pluck('id');
        $userIds = User::query()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->limit(200)->pluck('id');
        $clockPointIds = ClockPoint::query()->where('tenant_id', $tenantId)
            ->where('name', 'like', $like)->limit(200)->pluck('id');

        foreach ([
            [Worker::class, $workerIds, ['worker_id']],
            [Customer::class, $customerIds, ['customer_id']],
            [Location::class, $locationIds, ['location_id']],
            [InternalTeam::class, $teamIds, ['internal_team_id']],
            [User::class, $userIds, ['user_id']],
            [ClockPoint::class, $clockPointIds, ['clock_point_id', 'clock_in_clock_point_id']],
        ] as [$class, $ids, $payloadKeys]) {
            if ($ids->isEmpty()) {
                continue;
            }

            $query->orWhere(function ($q) use ($class, $ids, $payloadKeys): void {
                $q->where(fn ($inner) => $inner->where('model_type', $class)->whereIn('model_id', $ids));
                foreach ($payloadKeys as $key) {
                    $q->orWhereIn('payload->'.$key, $ids);
                }
            });
        }
    }
}

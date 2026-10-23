<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\Customer;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkShift;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns audit_logs rows into human sentences for the tenant activity page.
 * Resolves subjects in batches (no N+1); missing models fall back safely.
 */
final class DescribeTenantAuditLog
{
    /**
     * payload-velden die naar een onderwerp wijzen, in volgorde van voorkeur.
     *
     * @var array<string, class-string>
     */
    private const PAYLOAD_SUBJECT_KEYS = [
        'work_shift_id' => WorkShift::class,
        'worker_id' => Worker::class,
        'planned_shift_id' => PlannedShift::class,
        'customer_id' => Customer::class,
        'location_id' => Location::class,
        'unit_id' => Unit::class,
        'clock_point_id' => ClockPoint::class,
        'internal_team_id' => InternalTeam::class,
        'user_id' => User::class,
        'tenant_id' => Tenant::class,
    ];

    /**
     * @param  iterable<int, AuditLog>  $logs
     * @return Collection<int, array{title: string, meta: string, context: string|null, action: string}>
     */
    public function describeAll(iterable $logs): Collection
    {
        $logs = collect($logs)->values();
        $subjects = $this->resolveSubjects($logs);

        return $logs->mapWithKeys(
            fn (AuditLog $log): array => [$log->id => $this->describe($log, $subjects)],
        );
    }

    /**
     * @param  array<string, string>  $subjects
     * @return array{title: string, meta: string, context: string|null, action: string}
     */
    private function describe(AuditLog $log, array $subjects): array
    {
        $subjectKey = $this->subjectKeyFor($log);
        $subject = $subjectKey !== null ? ($subjects[$subjectKey] ?? null) : null;

        if ($subject === null && $subjectKey !== null) {
            $subject = $this->missingSubjectLabel($log, $subjectKey);
        }

        $actor = $log->user?->name
            ?? $log->user?->email
            ?? __('audit.actor_system');

        return [
            'title' => $this->sentenceFor($log, $actor, $subject),
            'meta' => $log->created_at?->timezone(config('app.timezone'))->locale(app()->getLocale())->isoFormat('ddd D MMM · HH:mm') ?? '',
            'context' => $this->contextFor($log, $subjectKey, $subjects),
            'action' => $log->action,
        ];
    }

    /**
     * Zinsvorm voor bekende acties; anders de vertaalde titel.
     */
    private function sentenceFor(AuditLog $log, string $actor, ?string $subject): string
    {
        $key = str_replace('.', '_', $log->action);

        /** @var mixed $sentences */
        $sentences = __('audit.sentences');
        if (is_array($sentences)) {
            $template = $sentences[$key] ?? null;
            if ($log->user_id === null && isset($sentences[$key.'_system'])) {
                $template = $sentences[$key.'_system'];
            }
            if (is_string($template) && $template !== '') {
                $payload = is_array($log->payload) ? $log->payload : [];

                return strtr($template, [
                    ':actor' => $actor,
                    ':subject' => $subject ?? __('audit.subjects.default'),
                    ':count' => (string) ($payload['target_count'] ?? ''),
                ]);
            }
        }

        /** @var mixed $actions */
        $actions = __('audit.actions');
        $title = is_array($actions) && isset($actions[$log->action]) && is_string($actions[$log->action])
            ? $actions[$log->action]
            : __('audit.unknown_action', ['action' => str_replace(['_', '.'], ' ', $log->action)]);

        return $subject !== null
            ? __('audit.sentence_fallback', ['title' => $title, 'subject' => $subject])
            : $title;
    }

    /**
     * @param  array<string, string>  $subjects
     */
    private function contextFor(AuditLog $log, ?string $subjectKey, array $subjects): ?string
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $parts = [];

        if ($subjectKey !== null) {
            [$class, $id] = explode(':', $subjectKey);
            $typeKey = 'audit.subjects.'.Str::snake(class_basename($class));

            /** @var mixed $typeLabel */
            $typeLabel = __($typeKey);
            $type = is_string($typeLabel) && $typeLabel !== $typeKey ? $typeLabel : __('audit.subjects.default');
            $parts[] = Str::ucfirst($type).' #'.$id;
        }

        foreach (['reason', 'description', 'name', 'target_name', 'email'] as $field) {
            $value = $payload[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $parts[] = ($field === 'reason'
                ? __('audit.details.reason', ['reason' => Str::limit(trim($value), 80)])
                : Str::limit(trim($value), 80));
        }

        if (isset($payload['target_count']) && is_numeric($payload['target_count'])) {
            $parts[] = __('audit.context_count', ['count' => (int) $payload['target_count']]);
        }

        $parts = array_values(array_unique(array_filter($parts)));

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * Sleutel van het onderwerp dat bij deze log hoort (model of payload-id).
     */
    private function subjectKeyFor(AuditLog $log): ?string
    {
        if (is_string($log->model_type) && $log->model_id !== null && (int) $log->model_id > 0) {
            return $log->model_type.':'.(int) $log->model_id;
        }

        $payload = is_array($log->payload) ? $log->payload : [];
        foreach (self::PAYLOAD_SUBJECT_KEYS as $field => $class) {
            $id = $payload[$field] ?? null;
            if (is_numeric($id) && (int) $id > 0) {
                return $class.':'.(int) $id;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array<string, string> "Class:id" => label
     */
    private function resolveSubjects(Collection $logs): array
    {
        $wanted = [];
        foreach ($logs as $log) {
            $key = $this->subjectKeyFor($log);
            if ($key !== null) {
                [$class, $id] = explode(':', $key);
                $wanted[$class][(int) $id] = true;
            }
        }

        $resolved = [];
        foreach ($wanted as $class => $ids) {
            $resolved += $this->resolveModelLabels($class, array_keys($ids));
        }

        return $resolved;
    }

    /**
     * @param  class-string  $class
     * @param  list<int>  $ids
     * @return array<string, string>
     */
    private function resolveModelLabels(string $class, array $ids): array
    {
        return match ($class) {
            Worker::class => Worker::query()->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Worker $m) => [$class.':'.$m->id => $m->displayName()])->all(),
            WorkShift::class => WorkShift::query()->with('worker')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (WorkShift $m) => [$class.':'.$m->id => $m->worker?->displayName()
                    ?? __('audit.subject_missing_plain')])->all(),
            PlannedShift::class => PlannedShift::query()->with('worker')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (PlannedShift $m) => [$class.':'.$m->id => $m->worker?->displayName()
                    ?? __('audit.subject_missing_plain')])->all(),
            Customer::class => Customer::query()->whereIn('id', $ids)->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [$class.':'.$id => (string) $name])->all(),
            Location::class => Location::query()->whereIn('id', $ids)->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [$class.':'.$id => (string) $name])->all(),
            Unit::class => Unit::query()->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (Unit $m) => [$class.':'.$m->id => $m->localizedName()])->all(),
            InternalTeam::class => InternalTeam::query()->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (InternalTeam $m) => [$class.':'.$m->id => $m->localizedName()])->all(),
            ClockPoint::class => ClockPoint::query()->whereIn('id', $ids)->get()
                ->mapWithKeys(fn (ClockPoint $m) => [$class.':'.$m->id => $m->attendancePlaceLabel()])->all(),
            User::class => User::query()->whereIn('id', $ids)->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [$class.':'.$id => (string) $name])->all(),
            Tenant::class => Tenant::query()->whereIn('id', $ids)->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [$class.':'.$id => (string) $name])->all(),
            default => [],
        };
    }

    private function missingSubjectLabel(AuditLog $log, string $subjectKey): string
    {
        [$class, $id] = explode(':', $subjectKey);
        $typeKey = 'audit.subjects.'.Str::snake(class_basename($class));

        /** @var mixed $label */
        $label = __($typeKey);
        $type = is_string($label) && $label !== $typeKey ? $label : __('audit.subjects.default');

        return __('audit.subject_missing', ['type' => $type, 'id' => $id]);
    }
}

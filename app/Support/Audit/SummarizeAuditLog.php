<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Str;

/**
 * Turns raw audit_logs rows into short, human-readable lines for platform UI.
 */
final class SummarizeAuditLog
{
    /**
     * @return array{title: string, meta: string, context: string|null, action: string}
     */
    public function handle(AuditLog $log): array
    {
        $title = $this->titleFor($log->action);
        $org = $log->tenant?->name
            ?? __('audit.org_platform');
        $actor = $log->user?->name
            ?? $log->user?->email
            ?? __('audit.actor_system');
        $when = $log->created_at?->timezone(config('app.timezone'))->format('d-m-Y H:i')
            ?? '';

        return [
            'title' => $title,
            'meta' => __('audit.meta', [
                'org' => $org,
                'actor' => $actor,
                'when' => $when,
            ]),
            'context' => $this->contextFor($log),
            'action' => $log->action,
        ];
    }

    private function titleFor(string $action): string
    {
        /** @var mixed $actions */
        $actions = __('audit.actions');
        if (is_array($actions) && isset($actions[$action]) && is_string($actions[$action]) && $actions[$action] !== '') {
            return $actions[$action];
        }

        return __('audit.unknown_action', [
            'action' => str_replace(['_', '.'], ' ', $action),
        ]);
    }

    private function contextFor(AuditLog $log): ?string
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $parts = [];

        if ($log->model_id !== null && (int) $log->model_id > 0) {
            $parts[] = '#'.$log->model_id;
        }

        foreach (['name', 'email', 'slug', 'original_filename', 'recipient_email', 'target_name'] as $field) {
            $value = $payload[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $parts[] = Str::limit(trim($value), 80);
        }

        if (isset($payload['target_count']) && is_numeric($payload['target_count'])) {
            $parts[] = __('audit.context_count', ['count' => (int) $payload['target_count']]);
        }

        if (isset($payload['description']) && is_string($payload['description']) && trim($payload['description']) !== '') {
            $parts[] = Str::limit(trim($payload['description']), 80);
        }

        $parts = array_values(array_unique($parts));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}

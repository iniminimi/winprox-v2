@props([
    'view',
    'showTeam' => false,
])

<div class="wp-time-presence-row wp-time-presence-row--absent">
    <div class="wp-time-presence-row__identity">
        <span class="wp-time-presence-row__copy">
            <span class="wp-time-presence-row__name">{{ $view->displayName }}</span>
            @if ($showTeam && filled($view->teamName))
                <span class="wp-muted wp-text-sm">{{ $view->teamName }}</span>
            @endif
        </span>
    </div>
    <span class="wp-time-presence-row__clock wp-tabular">
        {{ $view->viewedAt->timezone(config('app.timezone'))->format('d-m-Y H:i') }}
    </span>
</div>

@php
    use App\Enums\IssueSource;

    $teamNames = $issue->tasks->map(fn ($t) => $t->team?->name)->filter()->unique()->values();
    $cardTitle = collect([
        $issue->location?->name,
        $issue->unit?->name,
        __('issues.card.kind_nr', ['nr' => $issue->id]),
    ])->filter()->join(', ');
    $addressLine = $issue->location
        ? trim(($issue->location->country_code ?: 'BE').' '.$issue->location->formattedAddress())
        : '';
    $datetime = optional($issue->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i');
    $teamsLabel = $teamNames->isNotEmpty() ? $teamNames->join(', ') : __('issues.card.no_team');
    $metaLine = match ($issue->source) {
        IssueSource::Qr => __('issues.card.meta_via_team', [
            'source' => __('issues.card.report_source_qr'),
            'datetime' => $datetime,
            'teams' => $teamsLabel,
        ]),
        IssueSource::QrLocation => __('issues.card.meta_via_team', [
            'source' => __('issues.card.report_source_qr_location'),
            'datetime' => $datetime,
            'teams' => $teamsLabel,
        ]),
        default => __('issues.card.meta_by_team', [
            'name' => $issue->reporter_name ?: __('issues.card.unknown_reporter'),
            'datetime' => $datetime,
            'teams' => $teamsLabel,
        ]),
    };
@endphp
<a href="{{ route('issues.show', $issue) }}"
   @class(['wp-issue-row', 'wp-issue-row--highlight' => $highlight ?? false])
   wire:key="issue-{{ $issue->id }}">
    <div class="wp-grow wp-stack-tight">
        @if ($cardTitle !== '')
            <p class="wp-issue-card-title">{{ $cardTitle }}</p>
        @endif
        @if ($addressLine !== '')
            <p class="wp-issue-card-meta">{{ $addressLine }}</p>
        @endif
        <p class="wp-issue-card-meta">{{ $metaLine }}</p>
        @if ($issue->description)
            <p class="wp-issue-card-desc">
                <span class="wp-issue-card-desc-label">{{ __('issues.card.description_label') }}</span>{{ $issue->description }}
            </p>
        @endif
    </div>
    <div class="wp-issue-row-meta">
        @unless ($issue->isApproved())
            <span class="wp-pill wp-pill--progress">{{ __('issues.pending_review') }}</span>
        @endunless
        @if ($issue->is_recurring)
            <span class="wp-pill wp-pill--done">{{ __('issues.card.recurring') }}</span>
        @endif
        <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
    </div>
</a>

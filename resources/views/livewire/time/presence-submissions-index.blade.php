<div class="wp-stack" wire:poll.visible.30s>
    <x-wp-page-head-title
        :title="__('time.ciao.title')"
        help-page="time.ciao"
        :subtitle="__('time.ciao.subtitle')"
    />

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount, 'ciaoFailCount' => $ciaoFailCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    @error('retry')
        <div class="wp-flash wp-flash--danger">{{ $message }}</div>
    @enderror

    @unless ($complianceEnabled)
        <p class="wp-muted">{{ __('time.ciao.compliance_off') }}</p>
    @else
        <div class="wp-card wp-filter-panel">
            <div class="wp-filter-form">
                <div class="wp-filter-form__row">
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="ciao-search">{{ __('time.ciao.search_label') }}</label>
                        <input id="ciao-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search"
                               placeholder="{{ __('time.ciao.search_placeholder') }}">
                    </div>
                    <div class="wp-filter-cell">
                        <label class="wp-filter-inline-label" for="ciao-status">{{ __('time.ciao.status_label') }}</label>
                        <select id="ciao-status" class="wp-select" wire:model.live="statusFilter">
                            <option value="">{{ __('time.ciao.status_all') }}</option>
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status->value }}">
                                    {{ __('time.ciao.status.'.$status->value) }}
                                    ({{ (int) ($statusCounts[$status->value] ?? 0) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="wp-cluster wp-cluster--tight">
                    <button type="button" class="btn btn--sm {{ $statusFilter === null ? 'btn--primary' : 'btn--surface' }}"
                            wire:click="setStatusFilter('')">
                        {{ __('time.ciao.status_all') }}
                    </button>
                    @foreach ($statusOptions as $status)
                        <button type="button"
                                class="btn btn--sm {{ $statusFilter === $status->value ? 'btn--primary' : 'btn--surface' }}"
                                wire:click="setStatusFilter('{{ $status->value }}')">
                            {{ __('time.ciao.status.'.$status->value) }}
                            @if ((int) ($statusCounts[$status->value] ?? 0) > 0)
                                <span class="wp-pill wp-pill--progress">{{ (int) $statusCounts[$status->value] }}</span>
                            @endif
                        </button>
                    @endforeach
                    <x-wp-list-export :csv-url="$exportUrl" :print-url="$printUrl" />
                </div>
                <p class="wp-muted wp-text-sm">{{ __('time.ciao.retention_hint', ['months' => $retentionMonths]) }}</p>
            </div>
        </div>

        @if ($submissions->isEmpty())
            <p class="wp-muted">{{ __('time.ciao.empty') }}</p>
        @else
            @php
                $tz = config('app.timezone');
                $groups = $submissions->getCollection()->groupBy(function ($submission) use ($tz) {
                    $day = $submission->registration_at?->timezone($tz)->format('Y-m-d') ?? 'unknown';

                    return ((string) ($submission->worker_id ?? '0')).'|'.$day;
                });
            @endphp
            <div class="wp-list">
                @foreach ($groups as $groupKey => $groupItems)
                    @php
                        $first = $groupItems->first();
                        $dayLabel = $first->registration_at?->timezone($tz)->format('d-m-Y') ?? '—';
                    @endphp
                    <div class="wp-card wp-card-pad wp-stack-tight" wire:key="ciao-group-{{ $groupKey }}">
                        <p>
                            <strong>{{ $first->worker?->displayName() ?? '—' }}</strong>
                            <span class="wp-muted wp-text-sm">· {{ $dayLabel }}</span>
                        </p>
                        @foreach ($groupItems as $submission)
                            @php
                                $detail = null;
                                if ($submission->rsz_id) {
                                    $detail = 'RSZ #'.$submission->rsz_id;
                                    if ($submission->rsz_validity) {
                                        $detail .= ' · '.$submission->rsz_validity;
                                    }
                                } elseif ($submission->error_message) {
                                    $rawError = (string) $submission->error_message;
                                    $errorCode = explode(':', $rawError, 2)[0];
                                    $errorKey = 'time.ciao.errors.'.$errorCode;
                                    $translated = __($errorKey);
                                    $detail = $translated !== $errorKey ? $translated : $rawError;
                                }

                                $canRetry = in_array($submission->status, [
                                    \App\Enums\PresenceSubmissionStatus::Failed,
                                    \App\Enums\PresenceSubmissionStatus::Skipped,
                                    \App\Enums\PresenceSubmissionStatus::Pending,
                                ], true);
                            @endphp
                            <div class="wp-cluster wp-cluster--spread" wire:key="ciao-sub-{{ $submission->id }}">
                                <p @class([
                                    'wp-text-sm',
                                    'wp-muted' => $submission->status === \App\Enums\PresenceSubmissionStatus::Submitted
                                        || $submission->status === \App\Enums\PresenceSubmissionStatus::Pending,
                                    'wp-error' => $submission->status === \App\Enums\PresenceSubmissionStatus::Failed
                                        || $submission->status === \App\Enums\PresenceSubmissionStatus::Skipped,
                                ])>
                                    {{ $submission->registration_at?->timezone($tz)->format('H:i') }}
                                    {{ __('time.ciao.event.'.$submission->source_event->value) }}
                                    · {{ $submission->presence_type->value }}
                                    @if ($submission->location)
                                        · {{ $submission->location->name }}
                                    @endif
                                    @if ($detail)
                                        , {{ $detail }}
                                    @endif
                                </p>
                                <div class="wp-cluster wp-cluster--wrap">
                                    @if ($submission->status !== \App\Enums\PresenceSubmissionStatus::Submitted)
                                        <span @class([
                                            'wp-pill',
                                            'wp-pill--progress' => $submission->status === \App\Enums\PresenceSubmissionStatus::Pending,
                                            'wp-pill--new' => $submission->status === \App\Enums\PresenceSubmissionStatus::Failed,
                                            'wp-pill--closed' => $submission->status === \App\Enums\PresenceSubmissionStatus::Skipped,
                                        ])>
                                            {{ __('time.ciao.status.'.$submission->status->value) }}
                                        </span>
                                    @endif
                                    @can('retry', $submission)
                                        @if ($canRetry)
                                            <button type="button" class="btn btn--sm btn--surface"
                                                    wire:click="retry({{ $submission->id }})"
                                                    wire:loading.attr="disabled">
                                                {{ __('time.ciao.retry') }}
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="wp-pagination">
                {{ $submissions->links() }}
            </div>
        @endif
    @endunless
</div>

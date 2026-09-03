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
                </div>
            </div>
        </div>

        @if ($submissions->isEmpty())
            <p class="wp-muted">{{ __('time.ciao.empty') }}</p>
        @else
            <div class="wp-card wp-table-wrap">
                <table class="wp-table">
                    <thead>
                        <tr>
                            <th>{{ __('time.ciao.col.when') }}</th>
                            <th>{{ __('time.ciao.col.worker') }}</th>
                            <th>{{ __('time.ciao.col.event') }}</th>
                            <th>{{ __('time.ciao.col.type') }}</th>
                            <th>{{ __('time.ciao.col.status') }}</th>
                            <th>{{ __('time.ciao.col.detail') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($submissions as $submission)
                            <tr wire:key="ciao-sub-{{ $submission->id }}">
                                <td>{{ $submission->registration_at?->timezone(config('app.timezone'))->format('d-m-Y H:i') }}</td>
                                <td>{{ $submission->worker?->displayName() ?? '—' }}</td>
                                <td>{{ __('time.ciao.event.'.$submission->source_event->value) }}</td>
                                <td>{{ $submission->presence_type->value }}</td>
                                <td>
                                    <span @class([
                                        'wp-pill',
                                        'wp-pill--progress' => $submission->status === \App\Enums\PresenceSubmissionStatus::Pending,
                                        'wp-pill--done' => $submission->status === \App\Enums\PresenceSubmissionStatus::Submitted,
                                        'wp-pill--new' => $submission->status === \App\Enums\PresenceSubmissionStatus::Failed,
                                        'wp-pill--closed' => $submission->status === \App\Enums\PresenceSubmissionStatus::Skipped,
                                    ])>
                                        {{ __('time.ciao.status.'.$submission->status->value) }}
                                    </span>
                                </td>
                                <td class="wp-text-sm">
                                    @if ($submission->rsz_id)
                                        <span class="wp-muted">RSZ #{{ $submission->rsz_id }}</span>
                                        @if ($submission->rsz_validity)
                                            · {{ $submission->rsz_validity }}
                                        @endif
                                    @elseif ($submission->error_message)
                                        {{ __('time.ciao.errors.'.$submission->error_message) !== 'time.ciao.errors.'.$submission->error_message
                                            ? __('time.ciao.errors.'.$submission->error_message)
                                            : $submission->error_message }}
                                    @else
                                        —
                                    @endif
                                    @if ($submission->location)
                                        <div class="wp-muted">{{ $submission->location->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    @can('retry', $submission)
                                        @if (in_array($submission->status, [
                                            \App\Enums\PresenceSubmissionStatus::Failed,
                                            \App\Enums\PresenceSubmissionStatus::Skipped,
                                            \App\Enums\PresenceSubmissionStatus::Pending,
                                        ], true))
                                            <button type="button" class="btn btn--sm btn--surface"
                                                    wire:click="retry({{ $submission->id }})"
                                                    wire:loading.attr="disabled">
                                                {{ __('time.ciao.retry') }}
                                            </button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="wp-pagination">
                {{ $submissions->links() }}
            </div>
        @endif
    @endunless
</div>

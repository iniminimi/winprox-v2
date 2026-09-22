<div class="wp-stack" data-manual-capture="time-absence-requests">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                :title="__('time.absence.title')"
                help-page="time.absence_requests"
                :subtitle="__('time.absence.subtitle')"
            />
        </div>
    </div>

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount, 'pendingAbsenceCount' => $pendingCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    <div class="wp-cluster wp-cluster--tight" role="tablist" aria-label="{{ __('time.absence.status_filters') }}">
        @php
            $statusFilters = [
                '' => __('time.absence.status_all'),
                \App\Enums\AbsenceRequestStatus::Pending->value => __('time.absence.status.pending'),
                \App\Enums\AbsenceRequestStatus::Approved->value => __('time.absence.status.approved'),
                \App\Enums\AbsenceRequestStatus::Rejected->value => __('time.absence.status.rejected'),
                \App\Enums\AbsenceRequestStatus::Cancelled->value => __('time.absence.status.cancelled'),
            ];
        @endphp
        @foreach ($statusFilters as $value => $label)
            <button
                type="button"
                @class(['btn', 'btn--sm', ($statusFilter ?? '') === $value ? 'btn--primary' : 'btn--surface'])
                wire:click="setStatusFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-list wp-list--entity-rows">
            @forelse ($requests as $request)
                <div class="wp-issue-row" wire:key="absence-request-{{ $request->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">
                            <strong>{{ $request->worker?->displayName() }}</strong>
                            · {{ __('time.schedule.types.kinds.'.$request->kind->value) }}
                        </p>
                        <p class="wp-issue-card-meta">
                            {{ $request->date_from->toDateString() }}
                            @if ($request->date_from->toDateString() !== $request->date_to->toDateString())
                                – {{ $request->date_to->toDateString() }}
                            @endif
                            @if ($request->description)
                                · {{ $request->description }}
                            @endif
                        </p>
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <span @class([
                            'wp-pill',
                            'wp-pill--progress' => $request->status === \App\Enums\AbsenceRequestStatus::Pending,
                            'wp-pill--done' => $request->status === \App\Enums\AbsenceRequestStatus::Approved,
                            'wp-pill--closed' => $request->status !== \App\Enums\AbsenceRequestStatus::Pending
                                && $request->status !== \App\Enums\AbsenceRequestStatus::Approved,
                        ])>
                            {{ __('time.absence.status.'.$request->status->value) }}
                        </span>
                        @if ($request->status->isPending())
                            @can('decide', $request)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openDecide({{ $request->id }})">
                                    {{ __('time.absence.decide') }}
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('time.absence.empty') }}</p>
            @endforelse
        </div>

    </div>

    @if ($deciding instanceof \App\Models\AbsenceRequest)
        <x-wp-modal closeMethod="closeDecide">
            <form wire:submit.prevent class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-h2">{{ __('time.absence.decide_title') }}</h2>
                    <x-wp-modal-close wire:click="closeDecide" />
                </div>
                <p>
                    <strong>{{ $deciding->worker?->displayName() }}</strong>
                    · {{ __('time.schedule.types.kinds.'.$deciding->kind->value) }}
                    · {{ $deciding->date_from->toDateString() }}
                    @if ($deciding->date_from->toDateString() !== $deciding->date_to->toDateString())
                        – {{ $deciding->date_to->toDateString() }}
                    @endif
                </p>
                @if ($deciding->description)
                    <p>{{ $deciding->description }}</p>
                @endif

                <div class="wp-stack-tight">
                    <h3 class="wp-section-title">{{ __('time.absence.conflicts_title') }}</h3>
                    @if ($conflicts->isEmpty())
                        <p class="wp-muted">{{ __('time.absence.conflicts_empty') }}</p>
                    @else
                        <ul class="wp-list">
                            @foreach ($conflicts as $shift)
                                <li>
                                    {{ $shift->work_date->toDateString() }}
                                    · {{ $shift->displayValue() }}
                                    @if ($shift->start_time && $shift->end_time)
                                        · {{ $shift->start_time }}–{{ $shift->end_time }}
                                    @endif
                                    · {{ __('time.absence.roster_status.'.$shift->status->value) }}
                                </li>
                            @endforeach
                        </ul>
                        <p class="wp-muted">{{ __('time.absence.conflicts_help') }}</p>
                    @endif
                </div>

                <div class="wp-field" x-data="{ n: 0, max: {{ $reasonMax }} }">
                    <label class="wp-label" for="decision-reason">{{ __('time.absence.decision_reason') }}</label>
                    <textarea
                        id="decision-reason"
                        class="wp-textarea"
                        rows="3"
                        maxlength="{{ $reasonMax }}"
                        wire:model="decisionReason"
                        x-on:input="n = $el.value.length"
                    ></textarea>
                    @error('decisionReason') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-cluster wp-cluster--wrap">
                    <button type="button" class="btn btn--primary" wire:click="approve">{{ __('time.absence.approve') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="reject">{{ __('time.absence.reject') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="closeDecide">{{ __('common.button.cancel') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>

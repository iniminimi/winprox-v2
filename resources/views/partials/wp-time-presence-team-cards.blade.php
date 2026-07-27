@props(['teamBuckets', 'statusFilter'])

@php
    use App\Enums\TimePresenceStatusFilter;
@endphp

@if ($teamBuckets->isEmpty())
    <p class="wp-muted">{{ __('time.presence.empty_section') }}</p>
@else
    <div class="wp-time-presence-card-grid">
        @foreach ($teamBuckets as $bucket)
            <article class="wp-card wp-card-pad wp-stack wp-time-presence-summary-card" wire:key="presence-team-card-{{ $bucket->team->id }}">
                <div class="wp-time-presence-summary-card__head">
                    <h2 class="wp-section-title">{{ $bucket->team->localizedName() }}</h2>
                    @if ($bucket->attentionCount > 0)
                        <span class="wp-pill wp-pill--progress">{{ $bucket->attentionCount }}</span>
                    @endif
                </div>

                <dl class="wp-time-presence-summary-card__stats">
                    @if ($statusFilter !== TimePresenceStatusFilter::Break && $statusFilter !== TimePresenceStatusFilter::Absent)
                        <div>
                            <dt>{{ __('time.presence.kpi.active') }}</dt>
                            <dd class="wp-tabular">{{ $bucket->activeCount }}</dd>
                        </div>
                    @endif
                    @if ($statusFilter !== TimePresenceStatusFilter::Active && $statusFilter !== TimePresenceStatusFilter::Absent)
                        <div>
                            <dt>{{ __('time.presence.kpi.break') }}</dt>
                            <dd class="wp-tabular">{{ $bucket->breakCount }}</dd>
                        </div>
                    @endif
                    @if ($statusFilter === TimePresenceStatusFilter::Absent || $statusFilter === TimePresenceStatusFilter::All)
                        <div>
                            <dt>{{ __('time.presence.kpi.absent') }}</dt>
                            <dd class="wp-tabular">{{ $bucket->absentCount }}</dd>
                        </div>
                    @endif
                </dl>

                <button type="button" class="btn btn--surface btn--sm" wire:click="openTeamCard({{ $bucket->team->id }})">
                    {{ __('time.presence.view_team') }}
                    <x-wp-icon name="chevron-down" class="wp-time-presence-summary-card__icon" />
                </button>
            </article>
        @endforeach
    </div>
@endif

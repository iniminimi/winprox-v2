@props(['locationBuckets'])

@if ($locationBuckets->isEmpty())
    <p class="wp-muted">{{ __('time.presence.empty_locations') }}</p>
@else
    <div class="wp-time-presence-card-grid">
        @foreach ($locationBuckets as $bucket)
            <article class="wp-card wp-card-pad wp-stack wp-time-presence-summary-card" wire:key="presence-location-card-{{ $bucket->location?->id ?? 'unknown' }}">
                <div class="wp-time-presence-summary-card__head">
                    <h2 class="wp-section-title">{{ $bucket->label() }}</h2>
                    <span class="wp-pill wp-pill--done">{{ $bucket->clockedInCount }}</span>
                </div>

                <dl class="wp-time-presence-summary-card__stats">
                    <div>
                        <dt>{{ __('time.presence.kpi.active') }}</dt>
                        <dd class="wp-tabular">{{ $bucket->activeCount }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('time.presence.kpi.break') }}</dt>
                        <dd class="wp-tabular">{{ $bucket->breakCount }}</dd>
                    </div>
                    @if ($bucket->attentionCount > 0)
                        <div>
                            <dt>{{ __('time.presence.kpi.attention') }}</dt>
                            <dd class="wp-tabular wp-time-presence-team-summary__alert">{{ $bucket->attentionCount }}</dd>
                        </div>
                    @endif
                </dl>

                <button type="button"
                        class="btn btn--surface btn--sm"
                        @if ($bucket->location)
                            wire:click="openLocationCard({{ $bucket->location->id }})"
                        @else
                            wire:click="setViewMode('teams')"
                        @endif>
                    {{ __('time.presence.view_location') }}
                    <x-wp-icon name="chevron-down" class="wp-time-presence-summary-card__icon" />
                </button>
            </article>
        @endforeach
    </div>
@endif

<div class="wp-card wp-card-pad wp-stack wp-time-roster">
    <div class="wp-time-roster__head">
        <p class="wp-time-roster__count">{{ __('time.roster.count', ['count' => $roster->count]) }}</p>
        <p class="wp-muted wp-time-roster__updated">{{ now()->format('H:i') }}</p>
    </div>
    @if ($roster->count === 0)
        <p class="wp-muted">{{ __('time.roster.empty') }}</p>
    @else
        @foreach ($roster->byLocation as $locationName => $people)
            <section class="wp-stack-tight" wire:key="roster-loc-{{ md5((string) $locationName) }}">
                <h2 class="wp-section-title">{{ $locationName }}</h2>
                <ul class="wp-time-roster__list">
                    @foreach ($people as $person)
                        <li class="wp-time-roster__person" wire:key="roster-person-{{ $person->workerId }}">
                            <div class="wp-time-roster__name-row">
                                <span class="wp-time-roster__name">{{ $person->displayName }}</span>
                                <span class="wp-pill {{ $person->onBreak ? 'wp-pill--progress' : 'wp-pill--done' }}">
                                    {{ $person->onBreak ? __('time.presence.on_break') : __('time.presence.present') }}
                                </span>
                            </div>
                            <p class="wp-time-roster__meta wp-muted">
                                {{ __('time.roster.role.'.$person->roleKey) }}
                                @if ($person->teamName !== '')
                                    · {{ $person->teamName }}
                                @endif
                                @if ($person->clockPointName !== '')
                                    · {{ $person->clockPointName }}
                                @endif
                                @if ($person->clockedInAt !== '')
                                    · {{ $person->clockedInAt }}
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>

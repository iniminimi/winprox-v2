@if ($rows !== null)
    <x-wp-modal closeMethod="closeOpens" aria-labelledby="team-library-opens-title">
        <div class="wp-card wp-card-pad wp-stack wp-modal-card">
            <div class="wp-modal-head">
                <h2 id="team-library-opens-title" class="wp-section-title">{{ __('team.library.opens_title') }}</h2>
                <x-wp-modal-close wire:click="closeOpens" />
            </div>
            <p class="wp-muted wp-text-sm">{{ __('team.library.opens_hint') }}</p>
            <div class="wp-list">
                @forelse ($rows as $row)
                    <div class="wp-issue-row" wire:key="team-library-open-{{ $row['worker']->id }}">
                        <p class="wp-grow">{{ $row['worker']->displayName() }}</p>
                        <span @class(['wp-pill', 'wp-pill--done' => $row['opened'], 'wp-pill--new' => ! $row['opened']])>
                            {{ $row['opened'] ? __('team.library.opened') : __('team.library.not_opened') }}
                        </span>
                    </div>
                @empty
                    <p class="wp-muted">{{ __('team.library.opens_empty') }}</p>
                @endforelse
            </div>
        </div>
    </x-wp-modal>
@endif

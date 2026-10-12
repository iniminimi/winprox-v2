<div>
    @if ($visible)
        @if ($errorMessage !== '')
            <p class="wp-error wp-sidebar-clock-error">{{ $errorMessage }}</p>
        @endif
        <div class="wp-sidebar-clock">
            <button type="button" class="btn btn--primary btn--sm" wire:click="clock">
                {{ $clockedIn ? __('time.portal.clock.out') : __('time.portal.clock.in') }}
            </button>
            <button type="button" class="btn btn--surface btn--sm" wire:click="toggleBreak" @disabled(! $clockedIn)>
                {{ $onBreak ? __('time.backoffice_clock.end_break') : __('time.backoffice_clock.start_break') }}
            </button>
        </div>
    @endif
</div>

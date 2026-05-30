{{-- Teamleader: collega-icoon vrijgeven (eigen icoon eerst bevestigen). --}}
<div class="wp-card wp-card-pad wp-stack">
    <div class="wp-row">
        <h2 class="wp-section-title">{{ __('portal.teamleader.title') }}</h2>
        <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleReleasePanel">
            {{ $showReleasePanel ? __('common.button.cancel') : __('portal.teamleader.open') }}
        </button>
    </div>
    <p class="wp-muted">{{ __('portal.teamleader.hint') }}</p>

    @if ($showReleasePanel)
        <form wire:submit="releaseColleagueIcon" class="wp-stack">
            <div class="wp-field">
                <label class="wp-label">{{ __('portal.teamleader.confirm_own_icon') }}</label>
                <div class="wp-icon-grid">
                    @foreach (\App\Support\Portal\WorkerIcon::SLUGS as $slug)
                        <button type="button"
                                wire:key="release-tl-icon-{{ $slug }}"
                                wire:click="$set('release_teamleader_icon_slug', '{{ $slug }}')"
                                @class(['wp-icon-tile', 'is-selected' => $release_teamleader_icon_slug === $slug])
                                title="{{ \App\Support\Portal\WorkerIcon::label($slug) }}"
                                aria-label="{{ \App\Support\Portal\WorkerIcon::label($slug) }}">
                            <x-wp-worker-icon :slug="$slug" />
                        </button>
                    @endforeach
                </div>
                @error('release_teamleader_icon_slug') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            <div class="wp-filter-bar">
                <div class="wp-field wp-grow">
                    <label class="wp-label" for="release_first">{{ __('portal.worker.first_name') }}</label>
                    <input id="release_first" type="text" class="wp-input" wire:model="release_first_name" autocomplete="given-name">
                </div>
                <div class="wp-field wp-grow">
                    <label class="wp-label" for="release_last">{{ __('portal.worker.last_name') }}</label>
                    <input id="release_last" type="text" class="wp-input" wire:model="release_last_name" autocomplete="family-name">
                </div>
            </div>
            @error('release_first_name') <p class="wp-error">{{ $message }}</p> @enderror
            @error('release_last_name') <p class="wp-error">{{ $message }}</p> @enderror
            @error('release_identify') <p class="wp-error">{{ $message }}</p> @enderror

            <button type="submit" class="btn btn--warning btn--block" @disabled($release_teamleader_icon_slug === '')>
                {{ __('portal.teamleader.submit') }}
            </button>
        </form>
    @endif
</div>

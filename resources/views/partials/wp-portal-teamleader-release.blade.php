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

            @php($blockedColleagues = $this->blockedReleaseCandidates())

            @if ($blockedColleagues->isEmpty())
                <p class="wp-muted">{{ __('portal.teamleader.no_blocked_colleagues') }}</p>
            @else
                <div class="wp-field">
                    <label class="wp-label">{{ __('portal.teamleader.choose_blocked_colleague') }}</label>
                    <div class="wp-list wp-list--entity-rows">
                        @foreach ($blockedColleagues as $colleague)
                            <button type="button"
                                    wire:key="release-worker-{{ $colleague->id }}"
                                    wire:click="$set('release_worker_id', {{ $colleague->id }})"
                                    @class([
                                        'wp-release-worker-row',
                                        'is-selected' => $release_worker_id === $colleague->id,
                                    ])
                                    aria-pressed="{{ $release_worker_id === $colleague->id ? 'true' : 'false' }}">
                                <span class="wp-cluster">
                                    @if ($colleague->field_icon_slug)
                                        <x-wp-worker-icon :slug="$colleague->field_icon_slug" class="wp-release-worker-row__icon" />
                                    @endif
                                    <span class="wp-release-worker-row__name">{{ $colleague->displayName() }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @error('release_worker_id') <p class="wp-error">{{ $message }}</p> @enderror

            <button type="submit"
                    class="btn btn--warning btn--block"
                    @disabled($release_teamleader_icon_slug === '' || $release_worker_id === null || $blockedColleagues->isEmpty())>
                {{ __('portal.teamleader.submit') }}
            </button>
        </form>
    @endif
</div>

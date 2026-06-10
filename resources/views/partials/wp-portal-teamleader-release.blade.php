{{-- Teamleader: collega-icoon vrijgeven (eigen icoon eerst bevestigen). --}}
@php($blockedColleagues = $this->blockedReleaseCandidates())

<div class="wp-card wp-card-pad wp-stack">
    <div class="wp-row">
        <h2 class="wp-section-title">{{ __('portal.teamleader.title') }}</h2>
        @if ($blockedColleagues->isNotEmpty())
            <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleReleasePanel">
                {{ $showReleasePanel ? __('common.button.cancel') : __('portal.teamleader.open') }}
            </button>
        @endif
    </div>

    @if ($blockedColleagues->isEmpty())
        <p class="wp-muted">{{ __('portal.teamleader.no_blocked_colleagues') }}</p>
    @else
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

                @error('release_worker_id') <p class="wp-error">{{ $message }}</p> @enderror

                <button type="submit"
                        class="btn btn--warning btn--block"
                        @disabled($release_teamleader_icon_slug === '' || $release_worker_id === null)>
                    {{ __('portal.teamleader.submit') }}
                </button>
            </form>
        @endif
    @endif

    {{-- Worker management: ONLY on TeamPortal --}}
    @if ($isTeamPortal ?? false)
        <div class="wp-divider"></div>

        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('portal.teamleader.manage_workers_title') }}</h2>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="{{ $showManageWorkers ? 'closeManageWorkers' : 'openManageWorkers' }}">
                {{ $showManageWorkers ? __('common.button.close') : __('portal.teamleader.manage_workers_open') }}
            </button>
        </div>

        @if (! $showManageWorkers)
            <p class="wp-muted">{{ __('portal.teamleader.manage_workers_hint') }}</p>
        @else
            {{-- Current workers list --}}
            @if ($teamWorkers->isEmpty())
                <p class="wp-muted">{{ __('portal.teamleader.no_workers') }}</p>
            @else
                <div class="wp-list wp-list--entity-rows">
                    @foreach ($teamWorkers as $tw)
                        @if ($verifiedWorker && (int) $tw->id === (int) $verifiedWorker->id)
                            @continue
                        @endif
                        <div class="wp-cluster wp-cluster--tight wp-release-worker-row" wire:key="manage-worker-{{ $tw->id }}">
                            <span class="wp-cluster wp-cluster--tight">
                                @if ($tw->field_icon_slug)
                                    <x-wp-worker-icon :slug="$tw->field_icon_slug" class="wp-release-worker-row__icon" />
                                @endif
                                <span class="wp-release-worker-row__name">{{ $tw->displayName() }}</span>
                                @if ($tw->is_teamleader)
                                    <span class="wp-pill wp-pill--progress">{{ __('portal.teamleader.badge') }}</span>
                                @endif
                            </span>
                            <button type="button"
                                    class="btn btn--danger btn--sm"
                                    wire:click="removeWorker({{ $tw->id }})"
                                    wire:confirm="{{ __('portal.teamleader.delete_confirm', ['name' => $tw->displayName()]) }}"
                                    @disabled($verifiedWorker && (int) $verifiedWorker->id === (int) $tw->id)>
                                {{ __('portal.teamleader.delete') }}
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <h3 class="wp-section-title wp-section-title--sm">{{ __('portal.teamleader.add_worker_title') }}</h3>

            {{-- Add worker form --}}
            <form wire:submit="addWorker" class="wp-stack">
                <div class="wp-field">
                    <label class="wp-label" for="tl_new_first">{{ __('portal.teamleader.worker_first_name') }}</label>
                    <input id="tl_new_first" type="text" class="wp-input" wire:model="newWorkerFirstName" autocomplete="given-name">
                    @error('newWorkerFirstName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="tl_new_last">{{ __('portal.teamleader.worker_last_name') }}</label>
                    <input id="tl_new_last" type="text" class="wp-input" wire:model="newWorkerLastName" autocomplete="family-name">
                    @error('newWorkerLastName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary btn--block">
                    {{ __('portal.teamleader.add_worker') }}
                </button>
            </form>
        @endif
    @endif
</div>

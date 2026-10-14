{{-- Teamleader: collega-icoon vrijgeven (eigen icoon eerst bevestigen). --}}
@php($blockedColleagues = $this->blockedReleaseCandidates())

@if ($blockedColleagues->isNotEmpty() || ($isTeamPortal ?? false) || ($isTimePortal ?? false))
<div class="wp-card wp-card-pad wp-stack">
    @if ($blockedColleagues->isNotEmpty())
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('portal.teamleader.title') }}</h2>
            <button type="button" class="btn btn--ghost btn--sm" data-manual-capture-trigger="teamleader-release" wire:click="toggleReleasePanel">
                {{ $showReleasePanel ? __('common.button.cancel') : __('portal.teamleader.open') }}
            </button>
        </div>

        <p class="wp-muted">{{ __('portal.teamleader.hint') }}</p>

        @if ($showReleasePanel)
            <form wire:submit="releaseColleagueIcon" class="wp-stack" data-manual-capture="portal-teamleader-release">
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

    @if ($isTimePortal ?? false)
        @php($deviceColleagues = $this->clockDeviceReleaseCandidates())
        @if ($deviceColleagues->isNotEmpty())
            @if ($blockedColleagues->isNotEmpty())
                <div class="wp-divider"></div>
            @endif
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('portal.teamleader.device_title') }}</h2>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleDeviceReleasePanel">
                    {{ $showDeviceReleasePanel ? __('common.button.cancel') : __('portal.teamleader.device_open') }}
                </button>
            </div>
            <p class="wp-muted">{{ __('portal.teamleader.device_hint') }}</p>
            @if ($showDeviceReleasePanel)
                <form wire:submit="releaseColleagueClockDevice" class="wp-stack">
                    @if ($this->portalReleaseConfirmsWithPin())
                        <div class="wp-field">
                            <label class="wp-label" for="release_device_pin">{{ __('portal.teamleader.confirm_own_pin') }}</label>
                            <input id="release_device_pin" type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="4" class="wp-input" wire:model.live="release_device_pin">
                            @error('release_device_pin') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="wp-field">
                            <label class="wp-label">{{ __('portal.teamleader.confirm_own_icon') }}</label>
                            <div class="wp-icon-grid">
                                @foreach (\App\Support\Portal\WorkerIcon::SLUGS as $slug)
                                    <button type="button"
                                            wire:key="release-device-tl-icon-{{ $slug }}"
                                            wire:click="$set('release_device_teamleader_icon_slug', '{{ $slug }}')"
                                            @class(['wp-icon-tile', 'is-selected' => $release_device_teamleader_icon_slug === $slug])
                                            title="{{ \App\Support\Portal\WorkerIcon::label($slug) }}"
                                            aria-label="{{ \App\Support\Portal\WorkerIcon::label($slug) }}">
                                        <x-wp-worker-icon :slug="$slug" />
                                    </button>
                                @endforeach
                            </div>
                            @error('release_device_teamleader_icon_slug') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="wp-field">
                        <label class="wp-label">{{ __('portal.teamleader.choose_device_colleague') }}</label>
                        <div class="wp-list wp-list--entity-rows">
                            @foreach ($deviceColleagues as $colleague)
                                <button type="button"
                                        wire:key="release-device-worker-{{ $colleague->id }}"
                                        wire:click="$set('release_device_worker_id', {{ $colleague->id }})"
                                        @class([
                                            'wp-release-worker-row',
                                            'is-selected' => $release_device_worker_id === $colleague->id,
                                        ])>
                                    <span class="wp-release-worker-row__name">{{ $colleague->displayName() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @error('release_device_worker_id') <p class="wp-error">{{ $message }}</p> @enderror
                    <button type="submit"
                            class="btn btn--warning btn--block"
                            @disabled(($this->portalReleaseConfirmsWithPin() ? $release_device_pin === '' : $release_device_teamleader_icon_slug === '') || $release_device_worker_id === null)>
                        {{ __('portal.teamleader.device_submit') }}
                    </button>
                </form>
            @endif
        @endif
    @endif

    {{-- Worker management: Clock Point- en (legacy) teamportaal --}}
    @if (($isTeamPortal ?? false) || ($isTimePortal ?? false))
        @if ($blockedColleagues->isNotEmpty())
            <div class="wp-divider"></div>
        @endif

        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('portal.teamleader.manage_workers_title') }}</h2>
            <button type="button" class="btn btn--ghost btn--sm" data-manual-capture-trigger="teamleader-workers" wire:click="{{ $showManageWorkers ? 'closeManageWorkers' : 'openManageWorkers' }}">
                {{ $showManageWorkers ? __('common.button.close') : __('portal.teamleader.manage_workers_open') }}
            </button>
        </div>

        @if (! $showManageWorkers)
            <p class="wp-muted">{{ __('portal.teamleader.manage_workers_hint') }}</p>
        @else
            <div data-manual-capture="portal-teamleader-workers">
            {{-- Werknemerfoto: crop + compress client-side, daarna Livewire-upload (§7). --}}
            <input type="file"
                   id="tl-worker-photo-input"
                   class="sr-only"
                   accept="image/jpeg,image/png,image/webp"
                   capture="environment"
                   wire:ignore
                   x-on:change="
                       const input = $event.target;
                       const file = input.files?.[0];
                       if (!file) { return; }
                       const crop = typeof window.wpCropImageFile === 'function'
                           ? window.wpCropImageFile(file, {
                                 aspectRatio: 1,
                                 circle: true,
                                 title: @js(__('team.workers.photo_crop_title')),
                                 applyLabel: @js(__('team.workers.photo_crop_apply')),
                                 cancelLabel: @js(__('common.button.cancel')),
                             })
                           : Promise.resolve(file);
                       crop.then((cropped) => {
                           if (!cropped) { return null; }
                           return typeof window.wpCompressImageFile === 'function'
                               ? window.wpCompressImageFile(cropped, { maxDimension: 400, quality: 0.8 })
                               : cropped;
                       }).then((compressed) => {
                           if (!compressed) { return; }
                           $wire.upload('teamleaderPhoto', compressed);
                       }).finally(() => { input.value = ''; });
                   ">
            @error('teamleaderPhoto') <p class="wp-error">{{ $message }}</p> @enderror

            {{-- Current workers list --}}
            @if ($teamWorkers->isEmpty())
                <p class="wp-muted">{{ __('portal.teamleader.no_workers') }}</p>
            @else
                <div class="wp-list wp-list--entity-rows">
                    @foreach ($teamWorkers as $tw)
                        @php($twPhotoUrl = $tw->photoPublicUrl())
                        <div class="wp-cluster wp-cluster--tight wp-release-worker-row" wire:key="manage-worker-{{ $tw->id }}">
                            <span class="wp-cluster wp-cluster--tight">
                                @if ($twPhotoUrl !== null)
                                    <span class="wp-worker-avatar wp-worker-avatar--photo" aria-hidden="true">
                                        <img src="{{ $twPhotoUrl }}" alt="" class="wp-worker-avatar__img">
                                    </span>
                                @elseif ($tw->field_icon_slug)
                                    <x-wp-worker-icon :slug="$tw->field_icon_slug" class="wp-release-worker-row__icon" />
                                @endif
                                <span class="wp-release-worker-row__name">{{ $tw->displayName() }}</span>
                                @if ($tw->is_teamleader)
                                    <span class="wp-pill wp-pill--progress">{{ __('portal.teamleader.badge') }}</span>
                                @endif
                            </span>
                            <span class="wp-cluster wp-cluster--tight">
                                <button type="button"
                                        class="btn btn--surface btn--sm"
                                        x-on:click="$wire.set('photoTargetWorkerId', {{ $tw->id }}); document.getElementById('tl-worker-photo-input')?.click()">
                                    {{ $twPhotoUrl !== null ? __('portal.teamleader.photo_update') : __('portal.teamleader.photo_take') }}
                                </button>
                                @if ($twPhotoUrl !== null)
                                    <button type="button"
                                            class="btn btn--ghost btn--sm"
                                            wire:click="removeWorkerPhoto({{ $tw->id }})"
                                            wire:confirm="{{ __('portal.teamleader.photo_remove_confirm', ['name' => $tw->displayName()]) }}">
                                        {{ __('team.workers.photo_remove') }}
                                    </button>
                                @endif
                                @if ($this->teamleaderCanRemoveWorker($tw))
                                    <button type="button"
                                            class="btn btn--danger btn--sm"
                                            wire:click="removeWorker({{ $tw->id }})"
                                            wire:confirm="{{ __('portal.teamleader.delete_confirm', ['name' => $tw->displayName()]) }}">
                                        {{ __('portal.teamleader.delete') }}
                                    </button>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($showAddWorkerForm)
                <h3 class="wp-section-title wp-section-title--sm">{{ __('portal.teamleader.add_worker_title') }}</h3>

                <div class="wp-stack">
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
                    <button type="button" class="btn btn--primary btn--block" wire:click="addWorker">
                        {{ __('portal.teamleader.add_worker') }}
                    </button>
                </div>
            @else
                <button type="button" class="btn btn--ghost btn--block" wire:click="$set('showAddWorkerForm', true)">
                    {{ __('portal.teamleader.add_another_worker') }}
                </button>
            @endif
            </div>
        @endif
    @endif
</div>
@endif
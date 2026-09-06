<div
    class="wp-stack"
    data-manual-capture="{{ $this->isBackofficeSection() ? 'team-backoffice' : 'team' }}"
>
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="team"
                :title="$this->isBackofficeSection() ? __('team.backoffice.title') : __('team.teams_page.title')"
                :help-page="$this->isBackofficeSection() ? 'team.backoffice' : 'team.teams'"
                :subtitle="$this->isBackofficeSection() ? __('team.backoffice.subtitle') : __('team.teams_page.subtitle')"
            />
        </div>
        @unless ($this->isBackofficeSection())
            <div class="wp-cluster">
                <a href="{{ route('briefing.print') }}" target="_blank" rel="noopener noreferrer" class="btn btn--ghost btn--sm">{{ __('team.briefing') }}</a>
            </div>
        @endunless
    </div>

    @if ($workersImportNotice)
        <div @class([
            'wp-flash',
            'wp-flash--success' => $workersImportNoticeType !== 'error',
            'wp-flash--danger' => $workersImportNoticeType === 'error',
        ])>{{ $workersImportNotice }}</div>
    @endif

    @if ($this->isBackofficeSection())
    <div id="backoffice">
        @if ($canManageUsers)
            <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('team.colleagues.title') }}</h2>
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateColleague">
                    {{ __('team.colleagues.add') }}
                </button>
            </div>
            @error('colleagueCreate')
                <div class="wp-flash wp-flash--warning">{{ $message }}</div>
            @enderror
            <p class="wp-hint">{{ __('team.colleagues.invite_hint') }}</p>

            <div class="wp-list">
                @forelse ($colleagues as $colleague)
                    <div class="wp-data-row" wire:key="user-{{ $colleague->id }}">
                        <div class="wp-data-row-main">
                            <span class="wp-data-row-title">{{ $colleague->name }}</span>
                            <span class="wp-muted">{{ $colleague->email }}</span>
                        </div>
                        <div class="wp-cluster wp-cluster--tight">
                            <span class="wp-pill wp-pill--new">{{ $colleague->role === \App\Models\User::ROLE_ADMIN ? __('team.colleagues.role_admin') : __('team.colleagues.role_employee') }}</span>
                            <span class="wp-pill wp-pill--{{ $colleague->is_active ? 'done' : 'closed' }}">
                                {{ $colleague->is_active ? __('team.colleagues.active') : __('team.colleagues.inactive') }}
                            </span>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditColleague({{ $colleague->id }})">{{ __('team.colleagues.edit') }}</button>
                            @if ($colleague->id !== auth()->id())
                                @if ($colleague->is_active)
                                    <button type="button" class="btn btn--warning btn--sm" wire:click="setColleagueActive({{ $colleague->id }}, false)">{{ __('team.colleagues.deactivate') }}</button>
                                @else
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="setColleagueActive({{ $colleague->id }}, true)">{{ __('team.colleagues.activate') }}</button>
                                @endif
                            @else
                                <span class="wp-chip">{{ __('team.colleagues.you') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="wp-muted">{{ __('team.colleagues.empty') }}</p>
                @endforelse
            </div>
        </div>
        @endif
    </div>
    @else
    <x-wp-disclosure-card
        :title="__('unit_checks.lists.title')"
        :subtitle="__('team.checklists.click_to_manage')"
        :count="$checkLists->count()"
        entangle="showCheckListsSection"
    >
        <x-slot:toolbar>
            @can('create', App\Models\UnitCheckList::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateCheckList">
                    {{ __('unit_checks.lists.create') }}
                </button>
            @endcan
        </x-slot:toolbar>

        <p class="wp-muted wp-text-sm">{{ __('unit_checks.lists.lead') }}</p>
        @error('checkListName')
            <div class="wp-flash wp-flash--warning">{{ $message }}</div>
        @enderror

        @can('create', App\Models\UnitCheckList::class)
            @if ($checkListStarters !== [])
                <div class="wp-cluster">
                    <span class="wp-muted wp-text-sm">{{ __('unit_checks.lists.starters_label') }}</span>
                    @foreach ($checkListStarters as $starter)
                        <button
                            type="button"
                            class="btn btn--ghost btn--sm"
                            wire:click="copyCheckListFromStarter('{{ $starter['key'] }}')"
                        >
                            {{ __($starter['name']) }}
                        </button>
                    @endforeach
                </div>
            @endif
        @endcan

        @if ($checkLists->isNotEmpty())
            <div class="wp-list wp-list--entity-rows">
                @foreach ($checkLists as $list)
                    <div class="wp-issue-row" wire:key="unit-check-list-{{ $list->id }}">
                        <div class="wp-grow wp-stack-tight">
                            <div class="wp-cluster">
                                <p class="wp-issue-card-title">{{ $list->localizedName() }}</p>
                                @if (! $list->is_active)
                                    <span class="wp-pill wp-pill--closed">{{ __('unit_checks.lists.inactive') }}</span>
                                @endif
                            </div>
                            <p class="wp-muted wp-text-sm">
                                {{ $list->internalTeam?->localizedName() ?? __('unit_checks.lists.team_shared') }}
                                ·
                                {{ trans_choice('unit_checks.lists.item_count', $list->items_count, ['count' => $list->items_count]) }}
                            </p>
                        </div>
                        <div class="wp-cluster">
                            @can('update', $list)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditCheckList({{ $list->id }})">
                                    {{ __('common.button.edit') }}
                                </button>
                            @endcan
                            @can('delete', $list)
                                @if ($list->is_active)
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="deactivateCheckList({{ $list->id }})">
                                        {{ __('unit_checks.lists.deactivate') }}
                                    </button>
                                @endif
                                @if ($list->units_count === 0)
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="deleteCheckList({{ $list->id }})"
                                        wire:confirm="{{ __('unit_checks.lists.confirm_delete') }}"
                                    >
                                        {{ __('common.button.delete') }}
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="wp-muted">{{ __('unit_checks.lists.empty') }}</p>
        @endif
        </x-wp-disclosure-card>

    {{-- Teams ---------------------------------------------------------------}}
    <div id="teams" class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('team.teams.title') }}</h2>
            @if ($canManageTeams)
                <div class="wp-cluster">
                    @unless ($hasTimeModule)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openClockPointQr">
                            {{ __('team.clock_point_qr.button') }}
                        </button>
                    @endunless
                    @if ($canImportWorkers ?? false)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openWorkerImportModal">
                            {{ __('team.workers.import') }}
                        </button>
                    @endif
                    <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateTeam">
                        {{ __('team.teams.add') }}
                    </button>
                </div>
            @endif
        </div>
        <p class="wp-hint">{{ __('team.teams.hint') }}</p>

        <div class="wp-list">
            @forelse ($teams as $team)
                @php
                    $isTeamExpanded = in_array($team->id, $expandedTeamIds, true);
                @endphp
                <div class="wp-stack-tight {{ $isTeamExpanded ? 'wp-team-row--expanded' : '' }}" wire:key="team-{{ $team->id }}">
                    <div class="wp-data-row {{ $isTeamExpanded ? 'wp-team-header--expanded' : '' }}">
                        <div class="wp-data-row-main">
                            <button type="button"
                                    class="wp-team-row-toggle"
                                    wire:click="toggleTeam({{ $team->id }})"
                                    aria-expanded="{{ $isTeamExpanded ? 'true' : 'false' }}"
                                    aria-controls="team-panel-{{ $team->id }}">
                                <x-wp-icon name="chevron-down" class="wp-disclosure-chevron {{ $isTeamExpanded ? 'is-open' : '' }}" />
                                <span class="wp-data-row-title">{{ $team->localizedName() }}</span>
                            </button>
                            <span class="wp-muted">{{ __('team.teams.worker_count', ['count' => $team->workers->where('is_active', true)->count()]) }}</span>
                        </div>
                        <div class="wp-cluster wp-cluster--tight">
                            <span class="wp-pill wp-pill--{{ $team->is_active ? 'done' : 'closed' }}">{{ $team->is_active ? __('team.teams.active') : __('team.teams.inactive') }}</span>
                            @if ($canEditContent)
                                <button type="button" class="btn btn--surface btn--sm" wire:click="openEditTeam({{ $team->id }})">{{ __('team.teams.edit') }}</button>
                            @endif
                            @if ($canManageTeams)
                                @if ($team->workers->isEmpty())
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteTeam({{ $team->id }})"
                                            wire:confirm="{{ __('team.teams.confirm_delete') }}">{{ __('common.button.delete') }}</button>
                                @endif
                                @if ($team->is_active)
                                    <button type="button" class="btn btn--warning btn--sm" wire:click="setTeamActive({{ $team->id }}, false)">{{ __('team.teams.deactivate') }}</button>
                                @else
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="setTeamActive({{ $team->id }}, true)">{{ __('team.teams.activate') }}</button>
                                @endif
                            @endif
                        </div>
                    </div>

                    @if ($isTeamExpanded)
                        <div id="team-panel-{{ $team->id }}" class="wp-team-workers-panel wp-stack-tight">
                            @if ($canEditContent)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openAddWorker({{ $team->id }})">
                                    {{ __('team.workers.add') }}
                                </button>
                            @endif

                            <div class="wp-list">
                                @forelse ($team->workers as $worker)
                                    <div @class(['wp-data-row', 'wp-issue-row--highlight' => $highlightWorkerId === $worker->id]) wire:key="worker-{{ $worker->id }}">
                                        <div class="wp-data-row-identity">
                                            <x-wp-worker-avatar :worker="$worker" size="sm" :tone="$worker->is_active ? 'present' : 'absent'" />
                                            <div class="wp-data-row-main">
                                                <span class="wp-data-row-title">{{ $worker->displayName() }}</span>
                                                @if ($worker->company_name)
                                                    <span class="wp-muted">{{ $worker->company_name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="wp-cluster wp-cluster--tight">
                                            @if ($worker->is_external)
                                                <span class="wp-pill wp-pill--progress">{{ __('team.workers.external_badge') }}</span>
                                            @endif
                                            @unless ($worker->field_icon_slug)
                                                <span class="wp-pill wp-pill--progress">{{ __('team.workers.no_icon') }}</span>
                                            @endunless
                                            @if ($worker->is_teamleader)
                                                <span class="wp-pill wp-pill--done">{{ __('team.workers.teamleader') }}</span>
                                            @endif
                                            @if ($worker->clock_device_id)
                                                <span class="wp-pill wp-pill--done">{{ __('team.workers.device_bound') }}</span>
                                            @endif
                                            @if ($worker->field_icon_locked_at)
                                                <span class="wp-pill wp-pill--closed">{{ __('team.workers.locked') }}</span>
                                            @endif
                                            @unless ($worker->is_active)
                                                <span class="wp-pill wp-pill--closed">{{ __('team.workers.inactive') }}</span>
                                            @endunless
                                            @if ($canEditContent)
                                                <button type="button" class="btn btn--surface btn--sm" wire:click="openEditWorker({{ $worker->id }})">{{ __('common.button.edit') }}</button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="wp-muted">{{ __('team.workers.empty') }}</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <p class="wp-muted">{{ __('team.teams.empty') }}</p>
            @endforelse
        </div>
    </div>
    @endif

    {{-- Modal: collega-gebruiker --------------------------------------------}}
    @if ($canManageUsers && $showColleagueModal)
        <x-wp-modal closeMethod="cancelColleague">
            <form wire:submit="saveColleague" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <div class="wp-stack-tight">
                        <h2 class="wp-section-title">{{ $editingColleagueId ? __('team.colleagues.modal.edit_title') : __('team.colleagues.modal.create_title') }}</h2>
                        <p class="wp-muted wp-text-sm">{{ $editingColleagueId ? __('team.colleagues.modal.edit_subtitle') : __('team.colleagues.modal.create_subtitle') }}</p>
                    </div>
                    <x-wp-modal-close wire:click="cancelColleague" />
                </div>

                <div class="wp-modal-body wp-stack">
                    <div class="wp-field">
                        <input type="text" id="colleagueName" class="wp-input" wire:model="colleagueName"
                               placeholder="{{ __('team.colleagues.modal.placeholder_name') }}" autocomplete="name">
                        @error('colleagueName') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <input type="email" id="colleagueEmail" class="wp-input" wire:model="colleagueEmail"
                               placeholder="{{ __('team.colleagues.modal.placeholder_email') }}" autocomplete="email">
                        @error('colleagueEmail') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <select id="colleagueLocale" class="wp-select" wire:model="colleagueLocale" aria-label="{{ __('team.colleagues.modal.locale') }}">
                            @foreach (config('locales.supported', []) as $localeCode)
                                <option value="{{ $localeCode }}">{{ config('locales.labels.'.$localeCode, strtoupper($localeCode)) }}</option>
                            @endforeach
                        </select>
                        @error('colleagueLocale') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <select id="colleagueRole" class="wp-select" wire:model="colleagueRole" aria-label="{{ __('team.colleagues.modal.role') }}">
                            <option value="{{ \App\Models\User::ROLE_EMPLOYEE }}">{{ __('team.colleagues.role_employee') }}</option>
                            <option value="{{ \App\Models\User::ROLE_ADMIN }}">{{ __('team.colleagues.role_admin') }}</option>
                        </select>
                        @error('colleagueRole') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    @if ($colleagueRole === \App\Models\User::ROLE_EMPLOYEE)
                        <div class="wp-field">
                            <h3 class="wp-label">{{ __('team.colleagues.modal.locations_title') }}</h3>
                            <p class="wp-hint">{{ __('team.colleagues.modal.locations_hint') }}</p>
                            @if ($allLocations->isNotEmpty())
                                <div class="wp-grid wp-grid--2">
                                    @foreach ($allLocations as $location)
                                        <label class="wp-check">
                                            <input type="checkbox" wire:model="colleagueLocationIds" value="{{ $location->id }}">
                                            <span>{{ $location->name ?: $location->address }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="wp-muted">{{ __('team.colleagues.modal.locations_empty') }}</p>
                            @endif
                        </div>
                    @endif
                    @if ($hasTimeModule)
                        <div class="wp-field">
                            <label class="wp-label" for="colleaguePunchClockTeamId">{{ __('team.colleagues.modal.punch_clock_team') }}</label>
                            <select id="colleaguePunchClockTeamId" class="wp-select" wire:model="colleaguePunchClockTeamId">
                                <option value="">{{ __('team.colleagues.modal.punch_clock_team_none') }}</option>
                                @foreach ($punchClockTeams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                            <p class="wp-hint">{{ __('team.colleagues.modal.punch_clock_team_hint') }}</p>
                        </div>
                    @endif
                    <div class="wp-field">
                        <x-wp-password-input wireModel="colleaguePassword" id="colleaguePassword"
                                             placeholder="{{ __('team.colleagues.modal.placeholder_password') }}" />
                        @error('colleaguePassword') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <x-wp-password-input wireModel="colleaguePasswordConfirmation" id="colleaguePasswordConfirmation"
                                             placeholder="{{ __('team.colleagues.modal.placeholder_password_confirm') }}" />
                        @error('colleaguePasswordConfirmation') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    @if (! $editingColleagueId)
                        <label class="wp-check wp-check--boxed">
                            <input type="checkbox" wire:model="colleagueSendAccountEmail">
                            <span>{{ __('team.colleagues.modal.send_account_email') }}</span>
                        </label>
                    @else
                        <p class="wp-hint">{{ __('team.colleagues.modal.edit_password_hint') }}</p>
                    @endif

                    <label class="wp-check wp-check--boxed">
                        <input type="checkbox" wire:model="colleagueNotifyOnNewIssueEmail">
                        <span>
                            {{ __('team.colleagues.modal.notify_on_new_issue_email') }}
                            <br><span class="wp-hint">{{ __('team.colleagues.modal.notify_on_new_issue_email_hint') }}</span>
                        </span>
                    </label>
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="cancelColleague">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('team.colleagues.modal.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    {{-- Modal: worker bewerken/aanmaken --------------------------------------------}}
    @if ($showWorkerModal)
        <x-wp-modal closeMethod="cancelWorkerModal">
            <form wire:submit="saveWorker" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <div class="wp-stack-tight">
                        <h2 class="wp-section-title">{{ $editingWorkerId ? __('team.workers.modal.edit_title') : __('team.workers.modal.create_title') }}</h2>
                        <p class="wp-muted wp-text-sm">{{ $editingWorkerId ? __('team.workers.modal.edit_subtitle') : __('team.workers.modal.create_subtitle') }}</p>
                    </div>
                    <x-wp-modal-close wire:click="cancelWorkerModal" />
                </div>

                <div class="wp-modal-body wp-stack">
                    <div class="wp-field">
                        <span class="wp-label">{{ __('team.workers.photo') }}</span>
                        <p class="wp-hint">{{ __('team.workers.photo_hint') }}</p>
                        <div class="wp-worker-photo-picker">
                            @php
                                $photoPreviewUrl = $this->workerPhotoPreviewUrl();
                                $photoInitial = mb_strtoupper(mb_substr(trim($workerFirstName !== '' ? $workerFirstName : '?'), 0, 1));
                            @endphp
                            <span @class([
                                'wp-worker-avatar',
                                'wp-worker-avatar--lg',
                                'wp-worker-avatar--photo' => filled($photoPreviewUrl),
                            ]) aria-hidden="true">
                                @if (filled($photoPreviewUrl))
                                    <img src="{{ $photoPreviewUrl }}" alt="" class="wp-worker-avatar__img">
                                @else
                                    {{ $photoInitial }}
                                @endif
                            </span>
                            <div class="wp-stack-tight">
                                <input
                                    type="file"
                                    id="workerPhoto"
                                    class="wp-input"
                                    accept="image/jpeg,image/png,image/webp,image/*"
                                    x-on:change="
                                        const input = $event.target;
                                        const file = input.files?.[0];
                                        if (!file) {
                                            return;
                                        }
                                        const crop = typeof window.wpCropImageFile === 'function'
                                            ? window.wpCropImageFile(file, {
                                                aspectRatio: 1,
                                                title: @js(__('team.workers.photo_crop_title')),
                                                applyLabel: @js(__('team.workers.photo_crop_apply')),
                                                cancelLabel: @js(__('common.button.cancel')),
                                              })
                                            : Promise.resolve(file);
                                        crop.then((cropped) => {
                                            if (!cropped) {
                                                return null;
                                            }
                                            if (typeof window.wpCompressImageFile !== 'function') {
                                                return cropped;
                                            }
                                            return window.wpCompressImageFile(cropped, { maxDimension: 400, quality: 0.8 });
                                        }).then((compressed) => {
                                            if (!compressed) {
                                                return;
                                            }
                                            $wire.upload('workerPhoto', compressed, () => $wire.set('removeWorkerPhoto', false));
                                        }).finally(() => { input.value = ''; });
                                    "
                                >
                                @if (filled($photoPreviewUrl))
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="clearWorkerPhotoSelection"
                                    >
                                        {{ __('team.workers.photo_remove') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                        @error('workerPhoto') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="workerFirstName">{{ __('team.workers.first_name') }}</label>
                        <input type="text" id="workerFirstName" class="wp-input" wire:model="workerFirstName">
                        @error('workerFirstName') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="workerLastName">{{ __('team.workers.last_name') }}</label>
                        <input type="text" id="workerLastName" class="wp-input" wire:model="workerLastName">
                        @error('workerLastName') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="workerEmail">{{ __('team.workers.email') }}</label>
                        <input type="email" id="workerEmail" class="wp-input" wire:model="workerEmail">
                        @error('workerEmail') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="workerPhone">{{ __('team.workers.phone') }}</label>
                        <input type="tel" id="workerPhone" class="wp-input" wire:model="workerPhone">
                        @error('workerPhone') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    @if ($presenceComplianceEnabled)
                        <div>
                            <label class="wp-label" for="workerSsin">{{ __('team.workers.ssin') }}</label>
                            <input type="text" id="workerSsin" class="wp-input" wire:model="workerSsin" inputmode="numeric" autocomplete="off" maxlength="11">
                            <p class="wp-hint">{{ __('team.workers.ssin_hint') }}</p>
                            @error('workerSsin') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <label class="wp-check wp-check--boxed">
                        <input type="checkbox" wire:model.live="workerIsExternal">
                        <span>{{ __('team.workers.is_external') }}</span>
                    </label>
                    @if ($workerIsExternal)
                        <div class="wp-field">
                            <label class="wp-label" for="workerCompanyName">{{ __('team.workers.company_name') }}</label>
                            <input type="text" id="workerCompanyName" class="wp-input" wire:model="workerCompanyName" maxlength="120">
                            @error('workerCompanyName') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div class="wp-field">
                        <h3 class="wp-label">{{ __('team.workers.modal.locations_title') }}</h3>
                        <p class="wp-hint">{{ __('team.workers.modal.locations_hint') }}</p>
                        @if ($allLocations->isNotEmpty())
                            <div class="wp-grid wp-grid--2">
                                @foreach ($allLocations as $location)
                                    <label class="wp-check">
                                        <input type="checkbox" wire:model="selectedWorkerLocationIds" value="{{ $location->id }}">
                                        <span>{{ $location->name ?: $location->address }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="wp-muted">{{ __('team.workers.modal.locations_empty') }}</p>
                        @endif
                    </div>
                    @if ($editingWorkerId)
                        @php $editingWorker = $this->editingWorkerRecord(); @endphp
                        @if ($editingWorker)
                            <div class="wp-modal-section">
                                <h3 class="wp-label">{{ __('team.workers.modal.role_title') }}</h3>
                                <div class="wp-cluster wp-cluster--tight">
                                    @if ($editingWorker->is_teamleader)
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="setWorkerTeamleader({{ $editingWorker->id }}, false)">{{ __('team.workers.remove_teamleader') }}</button>
                                    @else
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="setWorkerTeamleader({{ $editingWorker->id }}, true)">{{ __('team.workers.make_teamleader') }}</button>
                                    @endif
                                </div>
                            </div>
                            <div class="wp-modal-section">
                                <h3 class="wp-label">{{ __('team.workers.modal.clock_title') }}</h3>
                                <p class="wp-hint">{{ __('team.workers.modal.clock_hint') }}</p>
                                <div class="wp-cluster wp-cluster--tight">
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="resetWorkerIcon({{ $editingWorker->id }})" wire:confirm="{{ __('team.workers.modal.confirm_reset_icon') }}">{{ __('team.workers.reset_icon') }}</button>
                                    @if ($editingWorker->clock_device_id)
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="clearWorkerClockDevice({{ $editingWorker->id }})" wire:confirm="{{ __('team.workers.modal.confirm_clear_clock_device') }}">{{ __('team.workers.clear_clock_device') }}</button>
                                    @endif
                                    @if ($editingWorker->hasClockPin())
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="clearWorkerClockPin({{ $editingWorker->id }})" wire:confirm="{{ __('team.workers.modal.confirm_clear_clock_pin') }}">{{ __('team.workers.clear_clock_pin') }}</button>
                                    @endif
                                </div>
                            </div>
                            <div class="wp-modal-section">
                                <h3 class="wp-label">{{ __('team.workers.modal.status_title') }}</h3>
                                <div class="wp-cluster wp-cluster--tight">
                                    @if ($editingWorker->is_active)
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="setWorkerActive({{ $editingWorker->id }}, false)" wire:confirm="{{ __('team.workers.modal.confirm_deactivate') }}">{{ __('team.workers.deactivate') }}</button>
                                    @else
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="setWorkerActive({{ $editingWorker->id }}, true)">{{ __('team.workers.activate') }}</button>
                                    @endif
                                    <button type="button" class="btn btn--danger btn--sm" wire:click="deleteWorker({{ $editingWorker->id }})" wire:confirm="{{ __('team.workers.modal.confirm_delete') }}">{{ __('common.button.delete') }}</button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="cancelWorkerModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('team.workers.modal.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @include('livewire.pages.worker-import-history', ['batches' => $workerImportBatches])

    {{-- Modal: worker CSV / Excel import ------------------------------------}}
    @if ($showWorkerImportModal)
        <x-wp-modal closeMethod="closeWorkerImportModal">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('team.workers.import_title') }}</h2>
                    <x-wp-modal-close wire:click="closeWorkerImportModal" />
                </div>
                <p class="wp-muted">{{ __('team.workers.import_hint') }}</p>

                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="downloadSampleCsv">
                        {{ __('team.workers.import_download_sample_csv') }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="downloadSampleXlsx">
                        {{ __('team.workers.import_download_sample_xlsx') }}
                    </button>
                </div>

                <form wire:submit="importWorkers" class="wp-stack" x-data="{ fileReady: false }">
                    <div class="wp-field">
                        <label class="wp-label" for="workerImportFile">{{ __('team.workers.import_file_label') }}</label>
                        <input type="file" id="workerImportFile" class="wp-input"
                            wire:model="workerImportFile"
                            x-on:change="fileReady = false"
                            x-on:livewire-upload-finish="fileReady = true"
                            x-on:livewire-upload-error="fileReady = false"
                            accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                        @error('workerImportFile') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    @if (!empty($workerImportErrors))
                        <div class="wp-stack-tight">
                            @foreach ($workerImportErrors as $error)
                                <p class="wp-error">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="wp-cluster">
                        <button type="submit" class="btn btn--primary"
                            :disabled="!fileReady"
                            wire:loading.attr="disabled"
                            wire:target="importWorkers,workerImportFile">
                            <span wire:loading wire:target="importWorkers,workerImportFile"><x-wp-spinner size="sm" /></span>
                            <span wire:loading.remove wire:target="importWorkers,workerImportFile">{{ __('team.workers.import_submit') }}</span>
                            <span wire:loading wire:target="importWorkers,workerImportFile">{{ __('team.workers.import_submit') }}</span>
                        </button>
                        <button type="button" class="btn btn--ghost" wire:click="closeWorkerImportModal">{{ __('common.button.cancel') }}</button>
                    </div>
                </form>
            </div>
        </x-wp-modal>
    @endif

    {{-- Modal: team ---------------------------------------------------------}}
    @if ($canEditContent && $showTeamModal)
        <x-wp-modal closeMethod="cancelTeam">
            <form wire:submit="saveTeam" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ $editingTeamId ? __('team.teams.modal.edit_title') : __('team.teams.modal.create_title') }}</h2>
                    <x-wp-modal-close wire:click="cancelTeam" />
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="teamName">{{ __('team.teams.modal.name') }}</label>
                    <input type="text" id="teamName" class="wp-input" wire:model="teamName">
                    @error('teamName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                @if ($editingTeamId !== null)
                    <div class="wp-field" x-data="{ open: false }">
                        <span class="wp-label">{{ __('team.teams.translation_edit.label') }}</span>

                        <div class="wp-field-panel" :class="{ 'is-open': open }">
                            <button
                                type="button"
                                class="wp-field-panel__trigger"
                                @click="open = !open"
                                :aria-expanded="open"
                            >
                                <span>{{ __('team.teams.translation_edit.open') }}</span>
                                <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                            </button>

                            <div class="wp-field-panel__body wp-stack-tight" x-show="open" x-cloak>
                                <div class="wp-cluster wp-issue-description-row">
                                    <select
                                        class="wp-select wp-select--compact"
                                        wire:model.live="teamPreviewLocale"
                                        aria-label="{{ __('issues.show.description_language') }}"
                                    >
                                        @foreach ($teamTranslationLocales as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <label class="wp-field">
                                    <span class="wp-label">{{ __('team.teams.translation_edit.name') }}</span>
                                    <textarea class="wp-input" wire:model="teamTranslationName" rows="1"></textarea>
                                    @error('teamTranslationName') <span class="wp-error">{{ $message }}</span> @enderror
                                </label>

                                <div class="wp-row">
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="saveTeamTranslationOverride"
                                        wire:loading.attr="disabled"
                                        wire:target="saveTeamTranslationOverride"
                                    >
                                        <span wire:loading wire:target="saveTeamTranslationOverride" class="wp-mr-2">
                                            <x-wp-spinner size="sm" />
                                        </span>
                                        <span>{{ __('team.teams.translation_edit.save') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="wp-field">
                    <label class="wp-label" for="teamSortOrder">{{ __('team.teams.modal.sort_order') }}</label>
                    <input type="number" id="teamSortOrder" class="wp-input" wire:model="teamSortOrder" min="0">
                    @error('teamSortOrder') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="teamSessionLifespanType">{{ __('team.teams.modal.session_lifespan_label') }}</label>
                    <select id="teamSessionLifespanType" class="wp-input" wire:model.live="teamSessionLifespanType">
                        <option value="daily">{{ __('team.teams.modal.session_lifespan_daily') }}</option>
                        <option value="weekly">{{ __('team.teams.modal.session_lifespan_weekly') }}</option>
                        <option value="custom">{{ __('team.teams.modal.session_lifespan_custom') }}</option>
                    </select>
                </div>
                @if ($teamSessionLifespanType === 'custom')
                    <div class="wp-field">
                        <label class="wp-label" for="teamSessionLifespanCustomHours">{{ __('team.teams.modal.session_lifespan_custom_hours') }}</label>
                        <input type="number" id="teamSessionLifespanCustomHours" class="wp-input" wire:model="teamSessionLifespanCustomHours" min="1">
                        @error('teamSessionLifespanCustomHours') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                @if ($canManageTeams)
                    <label class="wp-check">
                        <input type="checkbox" wire:model="teamIsActive">
                        {{ __('team.teams.modal.active') }}
                    </label>
                @endif
                <label class="wp-check">
                    <input type="checkbox" wire:model="teamClocksAllLocations">
                    {{ __('team.teams.modal.clocks_all_locations') }}
                </label>
                <p class="wp-hint">{{ __('team.teams.modal.clocks_all_locations_hint') }}</p>

                <div class="wp-field">
                    <h3 class="wp-label">{{ __('team.teams.modal.categories_title') }}</h3>
                    <p class="wp-hint">{{ __('team.teams.modal.categories_subtitle') }}</p>
                </div>

                @if ($categories->isNotEmpty())
                    <div class="wp-grid wp-grid--2">
                        @foreach ($categories as $category)
                            <label class="wp-check">
                                <input type="checkbox"
                                       wire:model.live="selectedCategoryIds"
                                       value="{{ $category->id }}">
                                <span>{{ $category->localizedName() }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="wp-muted">{{ __('team.teams.modal.categories_empty') }}</p>
                @endif

                <div class="wp-cluster wp-cluster--tight">
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="cancelTeam">{{ __('common.button.cancel') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showCheckListModal)
        <x-wp-modal closeMethod="closeCheckListModal">
            <form wire:submit="saveCheckList" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingCheckListId ? __('unit_checks.lists.edit_title') : __('unit_checks.lists.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeCheckListModal" />
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="checkListName">{{ __('unit_checks.lists.fields.name') }}</label>
                    <input type="text" id="checkListName" class="wp-input" wire:model="checkListName" maxlength="255">
                    @error('checkListName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                @if ($editingCheckListId !== null)
                    <div class="wp-field" x-data="{ open: false }">
                        <span class="wp-label">{{ __('unit_checks.lists.translation_edit.label') }}</span>

                        <div class="wp-field-panel" :class="{ 'is-open': open }">
                            <button
                                type="button"
                                class="wp-field-panel__trigger"
                                @click="open = !open"
                                :aria-expanded="open"
                            >
                                <span>{{ __('unit_checks.lists.translation_edit.open') }}</span>
                                <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                            </button>

                            <div class="wp-field-panel__body wp-stack-tight" x-show="open" x-cloak>
                                <div class="wp-cluster wp-issue-description-row">
                                    <select
                                        class="wp-select wp-select--compact"
                                        wire:model.live="checkListPreviewLocale"
                                        aria-label="{{ __('issues.show.description_language') }}"
                                    >
                                        @foreach ($checkListTranslationLocales as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <label class="wp-field">
                                    <span class="wp-label">{{ __('unit_checks.lists.translation_edit.name') }}</span>
                                    <textarea class="wp-input" wire:model="checkListTranslationName" rows="1"></textarea>
                                    @error('checkListTranslationName') <span class="wp-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="wp-field">
                                    <span class="wp-label">{{ __('unit_checks.lists.translation_edit.items') }}</span>
                                    <textarea
                                        class="wp-input"
                                        rows="6"
                                        wire:model="checkListTranslationItemsText"
                                        placeholder="{{ __('unit_checks.lists.fields.items_ph') }}"
                                    ></textarea>
                                    <p class="wp-hint">{{ __('unit_checks.lists.translation_edit.items_hint') }}</p>
                                    @error('checkListTranslationItemsText') <span class="wp-error">{{ $message }}</span> @enderror
                                </label>

                                <div class="wp-row">
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="saveCheckListTranslationOverride"
                                        wire:loading.attr="disabled"
                                        wire:target="saveCheckListTranslationOverride"
                                    >
                                        <span wire:loading wire:target="saveCheckListTranslationOverride" class="wp-mr-2">
                                            <x-wp-spinner size="sm" />
                                        </span>
                                        <span>{{ __('unit_checks.lists.translation_edit.save') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="wp-field">
                    <label class="wp-label" for="checkListTeamId">{{ __('unit_checks.lists.fields.team') }}</label>
                    <select id="checkListTeamId" class="wp-input" wire:model="checkListTeamId">
                        <option value="">{{ __('unit_checks.lists.fields.team_shared_option') }}</option>
                        @foreach ($checkListTeams as $teamOption)
                            <option value="{{ $teamOption->id }}">{{ $teamOption->localizedName() }}</option>
                        @endforeach
                    </select>
                    <p class="wp-hint">{{ __('unit_checks.lists.fields.team_hint') }}</p>
                    @error('checkListTeamId') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="checkListItemsText">{{ __('unit_checks.lists.fields.items') }}</label>
                    <textarea
                        id="checkListItemsText"
                        class="wp-input"
                        rows="6"
                        wire:model="checkListItemsText"
                        placeholder="{{ __('unit_checks.lists.fields.items_ph') }}"
                    ></textarea>
                    <p class="wp-hint">{{ __('unit_checks.lists.fields.items_hint') }}</p>
                    @error('checkListItemsText') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <label class="wp-check">
                    <input type="checkbox" wire:model="checkListIsActive">
                    {{ __('unit_checks.lists.fields.active') }}
                </label>

                <div class="wp-cluster wp-cluster--tight">
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                    <button type="button" class="btn btn--ghost" wire:click="closeCheckListModal">{{ __('common.button.cancel') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>

<div class="wp-stack">
    <x-wp-page-head-title
        icon="team"
        :title="__('team.title')"
        help-page="team"
        :subtitle="__('team.subtitle')"
    />

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

    {{-- Teams ---------------------------------------------------------------}}
    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('team.teams.title') }}</h2>
            @if ($canManageTeams)
                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openWorkerImportModal">
                        {{ __('team.workers.import') }}
                    </button>
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
                                <span class="wp-data-row-title">{{ $team->name }}</span>
                            </button>
                            <span class="wp-muted">{{ __('team.teams.worker_count', ['count' => $team->workers->where('is_active', true)->count()]) }}</span>
                        </div>
                        <div class="wp-cluster wp-cluster--tight">
                            <span class="wp-pill wp-pill--{{ $team->is_active ? 'done' : 'closed' }}">{{ $team->is_active ? __('team.teams.active') : __('team.teams.inactive') }}</span>
                            <a href="{{ route('team.qr', $team) }}" target="_blank" rel="noopener noreferrer" class="btn btn--surface btn--sm">{{ __('team.teams.qr') }}</a>
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
                            <div class="wp-row">
                                <span class="wp-label">{{ __('team.workers.title') }}</span>
                                @if ($canEditContent)
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openAddWorker({{ $team->id }})">
                                        <x-wp-icon name="plus" class="wp-icon" />
                                        <span>{{ __('team.workers.add') }}</span>
                                    </button>
                                @endif
                            </div>

                            @if ($canEditContent && $addingWorkerTeamId === $team->id)
                                <form wire:submit="saveWorker" class="wp-card wp-card-pad wp-stack-tight">
                                    <div class="wp-filter-bar">
                                        <div class="wp-field wp-grow">
                                            <label class="wp-label" for="workerFirstName-{{ $team->id }}">{{ __('team.workers.first_name') }}</label>
                                            <input type="text" id="workerFirstName-{{ $team->id }}" class="wp-input" wire:model="workerFirstName">
                                            @error('workerFirstName') <p class="wp-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="wp-field wp-grow">
                                            <label class="wp-label" for="workerLastName-{{ $team->id }}">{{ __('team.workers.last_name') }}</label>
                                            <input type="text" id="workerLastName-{{ $team->id }}" class="wp-input" wire:model="workerLastName">
                                            @error('workerLastName') <p class="wp-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="wp-filter-bar">
                                        <div class="wp-field wp-grow">
                                            <label class="wp-label" for="workerEmail-{{ $team->id }}">{{ __('team.workers.email') }}</label>
                                            <input type="email" id="workerEmail-{{ $team->id }}" class="wp-input" wire:model="workerEmail">
                                            @error('workerEmail') <p class="wp-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="wp-field wp-grow">
                                            <label class="wp-label" for="workerPhone-{{ $team->id }}">{{ __('team.workers.phone') }}</label>
                                            <input type="tel" id="workerPhone-{{ $team->id }}" class="wp-input" wire:model="workerPhone">
                                            @error('workerPhone') <p class="wp-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="wp-cluster wp-cluster--tight">
                                        <button type="submit" class="btn btn--primary btn--sm">{{ __('team.workers.add_submit') }}</button>
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="cancelWorker">{{ __('common.button.cancel') }}</button>
                                    </div>
                                </form>
                            @endif

                            <div class="wp-list">
                                @forelse ($team->workers as $worker)
                                    <div @class(['wp-data-row', 'wp-issue-row--highlight' => $highlightWorkerId === $worker->id]) wire:key="worker-{{ $worker->id }}">
                                        <div class="wp-data-row-main">
                                            <span class="wp-data-row-title">{{ $worker->displayName() }}</span>
                                            @unless ($worker->field_icon_slug)
                                                <span class="wp-muted">{{ __('team.workers.no_icon') }}</span>
                                            @endunless
                                        </div>
                                        <div class="wp-cluster wp-cluster--tight">
                                            @if ($worker->is_teamleader)
                                                <span class="wp-pill wp-pill--done">{{ __('team.workers.teamleader') }}</span>
                                            @endif
                                            @if ($worker->field_icon_locked_at)
                                                <span class="wp-pill wp-pill--closed">{{ __('team.workers.locked') }}</span>
                                            @endif
                                            @unless ($worker->is_active)
                                                <span class="wp-pill wp-pill--closed">{{ __('team.workers.inactive') }}</span>
                                            @endunless
                                            @if ($canEditContent)
                                                <button type="button" class="btn btn--surface btn--sm" wire:click="openEditWorker({{ $worker->id }})">{{ __('common.button.edit') }}</button>
                                                @if ($worker->is_teamleader)
                                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="setWorkerTeamleader({{ $worker->id }}, false)">{{ __('team.workers.remove_teamleader') }}</button>
                                                @else
                                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="setWorkerTeamleader({{ $worker->id }}, true)">{{ __('team.workers.make_teamleader') }}</button>
                                                @endif
                                                <button type="button" class="btn btn--ghost btn--sm" wire:click="resetWorkerIcon({{ $worker->id }})">{{ __('team.workers.reset_icon') }}</button>
                                                @if ($worker->is_active)
                                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="setWorkerActive({{ $worker->id }}, false)">{{ __('team.workers.deactivate') }}</button>
                                                @else
                                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="setWorkerActive({{ $worker->id }}, true)">{{ __('team.workers.activate') }}</button>
                                                @endif
                                                <button type="button" class="btn btn--danger btn--sm" wire:click="deleteWorker({{ $worker->id }})">{{ __('common.button.delete') }}</button>
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
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="cancelColleague">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('team.colleagues.modal.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    {{-- Modal: worker bewerken --------------------------------------------}}
    @if ($showWorkerModal)
        <x-wp-modal closeMethod="cancelWorkerEdit">
            <form wire:submit="saveWorkerEdit" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <div class="wp-stack-tight">
                        <h2 class="wp-section-title">{{ __('team.workers.modal.edit_title') }}</h2>
                        <p class="wp-muted wp-text-sm">{{ __('team.workers.modal.edit_subtitle') }}</p>
                    </div>
                    <x-wp-modal-close wire:click="cancelWorkerEdit" />
                </div>

                <div class="wp-modal-body wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="editWorkerFirstName">{{ __('team.workers.first_name') }}</label>
                        <input type="text" id="editWorkerFirstName" class="wp-input" wire:model="editWorkerFirstName">
                        @error('editWorkerFirstName') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="editWorkerLastName">{{ __('team.workers.last_name') }}</label>
                        <input type="text" id="editWorkerLastName" class="wp-input" wire:model="editWorkerLastName">
                        @error('editWorkerLastName') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="editWorkerEmail">{{ __('team.workers.email') }}</label>
                        <input type="email" id="editWorkerEmail" class="wp-input" wire:model="editWorkerEmail">
                        @error('editWorkerEmail') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="editWorkerPhone">{{ __('team.workers.phone') }}</label>
                        <input type="tel" id="editWorkerPhone" class="wp-input" wire:model="editWorkerPhone">
                        @error('editWorkerPhone') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="cancelWorkerEdit">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('team.workers.modal.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    <livewire:pages.worker-import-history />

    {{-- Modal: worker CSV import --------------------------------------------}}
    @if ($showWorkerImportModal)
        <x-wp-modal closeMethod="closeWorkerImportModal">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('team.workers.import_title') }}</h2>
                    <x-wp-modal-close wire:click="closeWorkerImportModal" />
                </div>
                <p class="wp-muted">{{ __('team.workers.import_hint') }}</p>

                <div class="wp-field">
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="downloadSampleCsv">
                        {{ __('team.workers.import_download_sample') }}
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
                            accept=".csv,.txt">
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
                                <span>{{ $category->name }}</span>
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
</div>

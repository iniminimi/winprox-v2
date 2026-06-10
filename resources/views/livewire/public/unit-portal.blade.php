<div class="wp-stack">
    @if ($inactiveReasonKey === null)
        <div wire:offline class="wp-flash wp-flash--offline" style="position: sticky; top: 0; z-index: 50; text-align: center;">
            {{ __('portal.offline_message') }}
        </div>
    @endif

    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">
                @php
                    $tenant = \App\Support\Tenancy::id() ? \App\Models\Tenant::find(\App\Support\Tenancy::id()) : null;
                    $logoUrl = $tenant ? $tenant->logoPublicUrl() : null;
                @endphp
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name ?? 'Logo' }}" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                @else
                    <img src="{{ asset('images/Winprox_logo_100.png') }}" alt="WinProx" style="max-width: 100px; max-height: 100px; object-fit: contain;">
                @endif
            </span>
            <div class="wp-cluster wp-cluster--tight">
                <x-wp-page-help page="portal.unit" />
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        <p class="wp-muted">
            @if ($locationName){{ $locationName }} &middot; @endif{{ $unitName }}
        </p>
        @if ($unitDescription)
            <p class="wp-muted">{{ $unitDescription }}</p>
        @endif

        {{-- GPS section - only for workers (citizens are already at location when they scan) --}}
        @if ($workerBelongsToUnitTeam)
            @php
                $unitModel = \App\Models\Unit::find($unitId);
                $mapsUrl = $unitModel?->googleMapsUrl();
            @endphp

            <div x-data="{ capturing: false }">
                {{-- Navigation button when GPS exists (hidden during capture) --}}
                @if ($mapsUrl)
                    <div style="margin-top: 0.75rem;" x-show="!capturing">
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="btn btn--ghost btn--block" style="justify-content: center;">
                            <svg class="wp-mr-2" style="width:1.25rem;height:1.25rem;vertical-align:middle;display:inline-block;" fill="#EA4335" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <span style="vertical-align:middle;display:inline-block;">{{ __('portal.worker.navigate_to_location') }}</span>
                        </a>
                    </div>
                @endif

                {{-- GPS Capture button - ALWAYS visible for re-capturing location --}}
                <div style="margin-top: 0.75rem;">
                    <button type="button" class="btn btn--primary btn--block" x-bind:disabled="capturing" @click="
                        capturing = true;
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(
                                (pos) => { $wire.gpsLatitude = pos.coords.latitude; $wire.gpsLongitude = pos.coords.longitude; $wire.updateUnitGps(); capturing = false; },
                                (err) => { alert('GPS fout: ' + err.message); capturing = false; }
                            );
                        } else {
                            alert('Geolocation wordt niet ondersteund'); capturing = false;
                        }
                    ">
                        <span x-show="!capturing">
                            <svg class="wp-mr-2" style="width:1.25rem;height:1.25rem;vertical-align:middle;display:inline-block;" fill="#EA4335" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <span style="vertical-align:middle;display:inline-block;">{{ $mapsUrl ? __('portal.unit.recapture_gps') : __('portal.unit.capture_gps') }}</span>
                        </span>
                        <span x-show="capturing">
                            <svg class="wp-mr-2" style="width:1.25rem;height:1.25rem;vertical-align:middle;display:inline-block;animation:wp-spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke-width="4" stroke-linecap="round" stroke-dasharray="31.42 31.42" transform="rotate(-90 12 12)"></circle>
                            </svg>
                            <span style="vertical-align:middle;display:inline-block;">{{ __('portal.unit.capturing_gps') }}</span>
                        </span>
                    </button>
                </div>

                @error('gpsLatitude') <span class="wp-error" style="text-align:center;display:block;">{{ $message }}</span> @enderror
                @error('gpsLongitude') <span class="wp-error" style="text-align:center;display:block;">{{ $message }}</span> @enderror
            </div>
        @endif
    </div>

    @if ($inactiveReasonKey !== null)
        <div class="wp-card wp-card-pad wp-stack">
            <h1 class="wp-section-title">{{ __('portal.inactive.title') }}</h1>
            <p class="wp-muted">{{ __($inactiveReasonKey) }}</p>
        </div>
    @else
        @if ($flashMessage !== '')
            <div class="wp-flash">{{ $flashMessage }}</div>
        @endif

        {{-- ============================ HOME ============================ --}}
        @if ($portalSection === 'home')
            <div class="wp-tiles">
                <button type="button" class="wp-tile wp-tile--primary" wire:click="openSection('new')">
                    <span class="wp-tile-title">{{ __('portal.tiles.new') }}</span>
                    <span class="wp-tile-sub">{{ __('portal.tiles.new_sub') }}</span>
                </button>
                <button type="button" class="wp-tile" wire:click="openSection('issues')">
                    <span class="wp-tile-title">{{ __('portal.tiles.issues') }} : {{ $issues->count() }}</span>
                </button>
                @if ($canAct)
                    <button type="button"
                            class="wp-tile"
                            @click="document.getElementById('portal-open-tasks')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                        <span class="wp-tile-title">{{ __('portal.tiles.open_tasks') }} : {{ $openTaskCount }}</span>
                    </button>
                @else
                    <div class="wp-tile wp-tile--static" aria-disabled="true">
                        <span class="wp-tile-title">{{ __('portal.tiles.open_tasks') }} : {{ $openTaskCount }}</span>
                    </div>
                @endif
                <button type="button" class="wp-tile" wire:click="openSection('announcements')">
                    <span class="wp-tile-title">{{ __('portal.tiles.announcements') }} : {{ $announcements->count() }}</span>
                </button>
                <button type="button" class="wp-tile" wire:click="openSection('documents')">
                    <span class="wp-tile-title">{{ __('portal.tiles.documents') }} : {{ $documents->count() }}</span>
                </button>
            </div>

            @if ($isFieldVisitor && $hasUnitTeam)
                @if ($canAct)
                    <div class="wp-card wp-card-pad wp-cluster">
                        @if ($worker?->field_icon_slug)
                            <div class="wp-icon-tile is-selected" aria-hidden="true" style="pointer-events: none; width: 40px; height: 40px; padding: 0.35rem;">
                                <x-wp-worker-icon :slug="$worker->field_icon_slug" />
                            </div>
                        @endif
                        <strong class="wp-text-body">{{ $worker?->displayName() }}</strong>
                    </div>
                    @if ($worker?->is_teamleader)
                        @include('partials.wp-portal-teamleader-release')
                    @endif

                    <div class="wp-row">
                        <h2 id="portal-open-tasks" class="wp-section-title">{{ __('portal.worker.open_tasks') }}</h2>
                        <button type="button" class="btn btn--surface btn--sm" wire:click="signInAsDifferentWorker">{{ __('portal.worker.sign_out') }}</button>
                    </div>
                    @forelse ($allOpenUnitTasks as $task)
                        @include('partials.wp-portal-task', ['task' => $task, 'team' => $team, 'worker' => $worker])
                    @empty
                        <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.worker.no_open_tasks') }}</p></div>
                    @endforelse
                @endif
            @endif
        @endif

        {{-- ============================ NEW ============================ --}}
        @if ($portalSection === 'new')
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
            <x-wp-page-head-title icon="issues" :title="__('portal.report.title')" />
            <form x-data="{ 
                isOffline: !navigator.onLine,
                description: $wire.description || sessionStorage.getItem('wp-portal-description') || ''
            }"
                  x-init="
                queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.());
                $watch('description', value => sessionStorage.setItem('wp-portal-description', value || ''));
                window.addEventListener('offline', () => isOffline = true);
                window.addEventListener('online', () => isOffline = false);
            "
                  @submit.prevent="
                await window.wpAwaitPhotoUploads($el);
                sessionStorage.removeItem('wp-portal-description');
                $wire.submitReport()
            "
                  class="wp-stack">
                <div class="wp-card wp-card-pad wp-stack">
                    @include('partials.wp-portal-report-reporter-fields')
                    <div class="wp-field">
                        <label class="wp-label" for="description">{{ __('portal.report.description') }}</label>
                        <textarea id="description" class="wp-textarea" x-model="description" wire:model="description" rows="5"
                                  placeholder="{{ __('portal.report.description_placeholder') }}"></textarea>
                        @error('description') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label">{{ __('portal.report.photos.label') }}</label>
                        @include('partials.wp-issue-photo-upload', ['model' => 'photos', 'preferCamera' => true])
                        @error('photos.*') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('photos') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-portal-actions">
                    <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled" :disabled="isOffline">
                        <x-wp-spinner wire:loading class="wp-mr-2" />
                        <span wire:loading.remove>{{ __('portal.report.submit') }}</span>
                        <span wire:loading>{{ __('portal.report.submit_loading') }}</span>
                    </button>
                </div>
            </form>
        @endif

        {{-- ======================= TAAK AFGEHANDELD ======================= --}}
        @if ($portalSection === 'task_done')
            <p class="wp-section-title">{{ __('portal.worker.task_completed') }}</p>
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
        @endif

        {{-- ============================ ISSUES ============================ --}}
        @if ($portalSection === 'issues')
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
            <x-wp-page-head-title icon="issues" :title="__('portal.tiles.issues')" />
            <div class="wp-list">
                @forelse ($issues as $issue)
                    @if ($issue->isApproved())
                        <button type="button" class="wp-card wp-card-pad wp-issue-link" wire:key="issue-{{ $issue->id }}" wire:click="openIssueDetail({{ $issue->id }})">
                            <div class="wp-cluster">
                                <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
                            </div>
                            <p class="wp-text-body">{{ __('portal.issue.list_line', [
                                'nr' => $issue->id,
                                'description' => \Illuminate\Support\Str::limit($issue->description, 100),
                            ]) }}</p>
                        </button>
                    @else
                        <div class="wp-card wp-card-pad wp-issue-card--pending" wire:key="issue-{{ $issue->id }}">
                            <div class="wp-cluster">
                                <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
                                <span class="wp-pill wp-pill--progress">{{ __('portal.pending_review') }}</span>
                            </div>
                            <p class="wp-text-body">{{ __('portal.worker.issue_heading', ['nr' => $issue->id]) }}</p>
                        </div>
                    @endif
                @empty
                    <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.issues_empty') }}</p></div>
                @endforelse
            </div>
        @endif

        {{-- ========================= ISSUE DETAIL ========================= --}}
        @if ($portalSection === 'issue_detail' && $selectedIssue)
            <button type="button" class="wp-back" wire:click="openSection('issues')">&larr; {{ __('portal.back') }}</button>
            <div class="wp-card wp-card-pad wp-stack">
                <div class="wp-cluster">
                    <span class="wp-pill wp-pill--{{ $selectedIssue->status->pillModifier() }}">{{ __($selectedIssue->status->labelKey()) }}</span>
                    <span class="wp-muted">{{ $selectedIssue->created_at?->isoFormat('D MMM YYYY') }}</span>
                </div>
                @if (! $selectedIssue->isApproved())
                    <p class="wp-muted">{{ __('portal.issue.awaiting_review_hint') }}</p>
                @endif

                @include('partials.wp-portal-issue-line', ['issue' => $selectedIssue])

                @include('partials.wp-portal-issue-photos', [
                    'issue' => $selectedIssue,
                    'wireKeyPrefix' => 'dp',
                ])

                @if ($selectedIssue->updates->isNotEmpty())
                    <h2 class="wp-section-title">{{ __('portal.issue.updates_title') }}</h2>
                    <div class="wp-stack">
                        @foreach ($selectedIssue->updates as $update)
                            <div class="wp-card wp-card-pad wp-stack-tight wp-surface-muted" wire:key="issue-update-{{ $update->id }}">
                                <p class="wp-muted">{{ $update->created_at?->isoFormat('D MMM YYYY, HH:mm') }}</p>
                                @if (filled($update->body))
                                    <p class="wp-text-body">{{ $update->body }}</p>
                                @elseif ($update->kind && $update->kind !== 'note')
                                    <p class="wp-text-body">{{ __('issues.updates.kind.'.$update->kind) }}</p>
                                @endif

                                @include('partials.wp-issue-photo-gallery', [
                                    'photos' => $update->photos,
                                    'wireKeyPrefix' => 'issue-update-photo-'.$update->id,
                                ])
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($canAct)
                <h2 class="wp-section-title">{{ __('portal.worker.open_tasks') }}</h2>
                @forelse ($openTasksForIssue as $task)
                    @include('partials.wp-portal-task', ['task' => $task, 'team' => $team, 'worker' => $worker])
                @empty
                    <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.worker.no_open_tasks') }}</p></div>
                @endforelse
            @endif
        @endif

        {{-- ========================== DOCUMENTS ========================== --}}
        @if ($portalSection === 'documents')
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
            <x-wp-page-head-title icon="document" :title="__('portal.tiles.documents')" />
            <div class="wp-list">
                @forelse ($documents as $document)
                    <div class="wp-card wp-card-pad wp-stack-tight" wire:key="doc-{{ $document->id }}">
                        <p class="wp-doc-title">{{ $document->title }}</p>
                        @if ($document->description)<p class="wp-muted">{{ $document->description }}</p>@endif
                        @if ($document->published_at)
                            <p class="wp-muted">{{ __('portal.documents.published', ['date' => $document->published_at->isoFormat('D MMM YYYY')]) }}</p>
                        @endif
                        @if ($document->isPubliclyDownloadable())
                            <a class="btn btn--ghost btn--sm" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank" rel="noopener">
                                {{ __('portal.documents.download') }}
                            </a>
                        @elseif ($document->requires_verification)
                            <span class="wp-chip">{{ __('portal.documents.verification_required') }}</span>
                            @if ($canAct)
                                <a class="btn btn--ghost btn--sm" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank" rel="noopener">
                                    {{ __('portal.documents.download') }}
                                </a>
                            @endif
                        @else
                            <span class="wp-chip">{{ __('portal.documents.staff_only') }}</span>
                        @endif
                    </div>
                @empty
                    <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.documents.empty') }}</p></div>
                @endforelse
            </div>
        @endif

        {{-- ======================== ANNOUNCEMENTS ======================== --}}
        @if ($portalSection === 'announcements')
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
            <x-wp-page-head-title icon="document" :title="__('portal.tiles.announcements')" />
            <div class="wp-list">
                @forelse ($announcements as $announcement)
                    <div class="wp-card wp-card-pad wp-stack-tight" wire:key="ann-{{ $announcement->id }}">
                        <p class="wp-doc-title">{{ $announcement->title }}</p>
                        <p class="wp-text-body">{{ $announcement->body }}</p>
                        <p class="wp-muted">{{ $announcement->published_at?->isoFormat('D MMM YYYY') }}</p>
                    </div>
                @empty
                    <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.announcements.empty') }}</p></div>
                @endforelse
            </div>
        @endif

        {{-- ==================== UNIT BACKGROUND PHOTO ==================== --}}
        @if ($portalSection === 'home' && $workerBelongsToUnitTeam)
            @php
                $bgTempCount = count($backgroundPhoto);
            @endphp
            <div class="wp-card wp-card-pad wp-stack" wire:key="unit-bg-photo-section">
                <h2 class="wp-section-title">{{ __('portal.unit.background_photo_title') }}</h2>

                @if ($unitBackgroundUrl)
                    <div class="wp-photo-grid wp-photo-grid--gallery">
                        <div class="wp-photo-thumb">
                            <img src="{{ $unitBackgroundUrl }}" alt="" width="80" height="80" loading="lazy">
                        </div>
                    </div>
                @endif

                @if ($bgTempCount > 0)
                    <div class="wp-photo-grid wp-photo-grid--gallery">
                        @foreach ($backgroundPhoto as $index => $photo)
                            <div class="wp-photo-thumb" style="position:relative;" wire:key="bg-temp-photo-{{ $index }}">
                                <img src="{{ $photo->temporaryUrl() }}" alt="" width="80" height="80" loading="lazy">
                                <button
                                    type="button"
                                    class="btn btn--danger btn--sm"
                                    style="position:absolute;top:2px;right:2px;padding:2px 6px;font-size:10px;"
                                    wire:click="removeBackgroundPhoto({{ $index }})"
                                >×</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($bgTempCount === 0 && count($backgroundPhoto) < 1)
                    @include('partials.wp-issue-photo-upload', ['model' => 'backgroundPhoto', 'max' => 1, 'preferCamera' => true, 'removeMethod' => 'removeBackgroundPhoto', 'photoAlt' => __('portal.unit.background_photo_add')])
                    <p class="wp-hint">{{ __('portal.unit.background_photo_hint') }}</p>
                @endif

                @error('backgroundPhoto') <p class="wp-error">{{ $message }}</p> @enderror
                @error('backgroundPhoto.0') <p class="wp-error">{{ $message }}</p> @enderror

                @if ($bgTempCount > 0)
                    <form
                        x-data="{ isOffline: !navigator.onLine }"
                        x-init="
                            window.addEventListener('offline', () => isOffline = true);
                            window.addEventListener('online', () => isOffline = false);
                        "
                        @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.uploadBackgroundPhoto()"
                        wire:key="bg-photo-submit-form"
                    >
                        <div class="wp-portal-actions">
                            <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled" :disabled="isOffline">
                                <x-wp-spinner wire:loading class="wp-mr-2" />
                                <span wire:loading.remove>{{ __('portal.unit.background_photo_save') }}</span>
                                <span wire:loading>{{ __('portal.unit.background_photo_saving') }}</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        {{-- ==================== QR-LINK PHOTOS (BOTTOM) ==================== --}}
        {{-- Show for workers (to add photos) OR for anyone if photos exist --}}
        @if ($portalSection === 'home' && ($workerBelongsToUnitTeam || $qrLinkPhotos->isNotEmpty()))
            @php
                $portalStoredCount = $qrLinkPhotos->count();
                $portalTempCount = count($newPortalPhotos);
                $portalTotalCount = $portalStoredCount + $portalTempCount;
                $portalCanAddMore = $portalTotalCount < 4;
            @endphp

            <div class="wp-card wp-card-pad wp-stack" x-data="{ open: false }" wire:key="qr-photos-accordion-{{ $portalTotalCount }}">
                <button
                    type="button"
                    class="wp-row"
                    style="width:100%;background:none;border:none;padding:0;cursor:pointer;"
                    @click="open = !open"
                    :aria-expanded="open"
                >
                    <h2 class="wp-section-title" style="margin:0;">{{ __('portal.unit.current_photos') }}</h2>
                    <x-wp-icon name="chevron-down" class="wp-icon" x-show="!open" />
                    <x-wp-icon name="chevron-up" class="wp-icon" x-show="open" x-cloak />
                </button>

                <div x-show="open" x-transition wire:key="qr-photos-content-{{ $portalTotalCount }}">
                    @if ($qrLinkPhotos->isNotEmpty())
                        <div class="wp-photo-grid wp-photo-grid--gallery" x-data="{ lightboxSrc: null }" @keydown.escape.window="lightboxSrc = null">
                            @foreach ($qrLinkPhotos as $photo)
                                @if ($photo->hasPublicFile())
                                    <div
                                        class="wp-photo-thumb"
                                        style="position:relative;"
                                        wire:key="qr-photo-{{ $photo->id }}"
                                    >
                                        <button
                                            type="button"
                                            style="background:none;border:none;padding:0;width:100%;height:100%;"
                                            @click="lightboxSrc = @js($photo->publicUrl())"
                                            aria-label="{{ __('issues.show.photo_enlarge') }}"
                                        >
                                            <img src="{{ $photo->publicUrl() }}" alt="" width="80" height="80" loading="lazy" x-on:error="$el.closest('.wp-photo-thumb')?.remove()">
                                        </button>

                                        @if ($workerBelongsToUnitTeam)
                                            <button
                                                type="button"
                                                class="btn btn--danger btn--sm"
                                                style="position:absolute;top:2px;right:2px;padding:2px 6px;font-size:10px;"
                                                wire:confirm="{{ __('portal.unit.delete_photo_confirm') }}"
                                                wire:click="removeUnitPhoto({{ $photo->id }})"
                                            >×</button>
                                        @endif
                                    </div>
                                @endif
                            @endforeach

                            <div
                                class="wp-photo-lightbox"
                                x-show="lightboxSrc"
                                x-cloak
                                x-transition.opacity
                                role="dialog"
                                aria-modal="true"
                                @click="lightboxSrc = null"
                            >
                                <img :src="lightboxSrc" alt="" @click.stop>
                            </div>
                        </div>
                    @endif

                    @if ($workerBelongsToUnitTeam)
                        @if ($portalTempCount > 0)
                            <div class="wp-photo-grid wp-photo-grid--gallery">
                                @foreach ($newPortalPhotos as $index => $photo)
                                    <div class="wp-photo-thumb" style="position:relative;" wire:key="portal-temp-photo-{{ $index }}">
                                        <img src="{{ $photo->temporaryUrl() }}" alt="" width="80" height="80" loading="lazy">
                                        <button
                                            type="button"
                                            class="btn btn--danger btn--sm"
                                            style="position:absolute;top:2px;right:2px;padding:2px 6px;font-size:10px;"
                                            wire:click="removeNewPortalPhoto({{ $index }})"
                                        >×</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($portalCanAddMore)
                            @include('partials.wp-issue-photo-upload', ['model' => 'newPortalPhotos', 'preferCamera' => true])
                            <p class="wp-hint">{{ __('portal.unit.update_photos_hint') }}</p>
                        @endif

                        @error('newPortalPhotos') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('newPortalPhotos.*') <p class="wp-error">{{ $message }}</p> @enderror

                        @if ($portalTempCount > 0)
                            <form
                                x-data="{ isOffline: !navigator.onLine }"
                                x-init="
                                    window.addEventListener('offline', () => isOffline = true);
                                    window.addEventListener('online', () => isOffline = false);
                                "
                                @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.updateUnitPhotos()"
                                wire:key="portal-photo-submit-form"
                            >
                                <div class="wp-portal-actions">
                                    <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled" :disabled="isOffline">
                                        <x-wp-spinner wire:loading class="wp-mr-2" />
                                        <span wire:loading.remove>{{ __('portal.unit.photos_submit') }}</span>
                                        <span wire:loading>{{ __('portal.unit.photos_submit_loading') }}</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    @else
                        <p class="wp-hint">{{ __('portal.unit.view_only_hint') }}</p>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>

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
                <x-wp-page-help page="portal.unit" :replace="['tenant' => $tenantName]" />
                @include('partials.wp-portal-theme')
                @include('partials.wp-portal-lang')
            </div>
        </div>
        <h1 class="wp-portal-welcome-title">{{ __('portal.welcome_title', ['tenant' => $tenantName]) }}</h1>

        @if ($canCaptureUnitGps || ($mapsUrl && $canAct))
            <div x-data="{ capturing: false }" class="wp-portal-gps">
                @if ($mapsUrl && $canAct)
                    <div class="wp-portal-gps__nav" x-show="!capturing">
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="btn btn--ghost btn--block wp-portal-gps__nav-link">
                            <span class="wp-portal-gps__action-inner">
                                <span class="wp-icon-frame" aria-hidden="true">
                                    <x-wp-icon name="map-pin" />
                                </span>
                                <span>{{ __('portal.worker.navigate_to_location') }}</span>
                            </span>
                        </a>
                    </div>
                @endif

                @if ($canCaptureUnitGps)
                    <div class="wp-portal-gps__capture">
                        <button type="button" class="btn btn--primary btn--block" x-bind:disabled="capturing" @click="
                            capturing = true;
                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(
                                    (pos) => {
                                        const d = new Date();
                                        const pad = (n) => String(n).padStart(2, '0');
                                        const tz = -d.getTimezoneOffset();
                                        const sign = tz >= 0 ? '+' : '-';
                                        const tzH = pad(Math.floor(Math.abs(tz) / 60));
                                        const tzM = pad(Math.abs(tz) % 60);
                                        $wire.gpsLatitude = pos.coords.latitude;
                                        $wire.gpsLongitude = pos.coords.longitude;
                                        $wire.gpsReportedAt = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}${sign}${tzH}:${tzM}`;
                                        $wire.updateUnitGps();
                                        capturing = false;
                                    },
                                    () => { alert(@js(__('qr.connect.gps_error'))); capturing = false; }
                                );
                            } else {
                                alert(@js(__('qr.connect.gps_not_supported'))); capturing = false;
                            }
                        ">
                            <span x-show="!capturing" class="wp-portal-gps__action-inner">
                                <span class="wp-icon-frame" aria-hidden="true">
                                    <x-wp-icon name="map-pin" />
                                </span>
                                <span>{{ $mapsUrl ? __('portal.unit.recapture_gps') : __('portal.unit.capture_gps') }}</span>
                            </span>
                            <span x-show="capturing" class="wp-cluster wp-cluster--tight" style="justify-content:center;">
                                <x-wp-spinner size="sm" />
                                <span>{{ __('portal.unit.capturing_gps') }}</span>
                            </span>
                        </button>
                    </div>
                @endif

                @error('gpsLatitude') <span class="wp-error wp-portal-gps__error">{{ $message }}</span> @enderror
                @error('gpsLongitude') <span class="wp-error wp-portal-gps__error">{{ $message }}</span> @enderror
                @error('gpsReportedAt') <span class="wp-error wp-portal-gps__error">{{ $message }}</span> @enderror
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
            <div data-manual-capture="portal-unit-home">
            <div class="wp-card wp-card-pad wp-portal-unit-context">
                <p class="wp-portal-unit-name">
                    @if ($locationName)<span>{{ $locationName }}</span> &middot; @endif<span>{{ $unitName }}</span>
                </p>
                @if ($unitDescription)
                    <p class="wp-muted">{{ $unitDescription }}</p>
                @endif
            </div>

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
                    <div class="wp-portal-worker-actions">
                        <button type="button" class="btn btn--surface btn--sm" wire:click="signInAsDifferentWorker">{{ __('portal.worker.sign_out') }}</button>
                    </div>
                    @if ($worker?->is_teamleader)
                        @include('partials.wp-portal-teamleader-release')
                    @endif

                    <div
                        class="wp-card wp-card-pad wp-stack"
                        wire:key="portal-open-tasks-card"
                        data-manual-capture="portal-unit-worker-tasks"
                        x-data="{ open: false, hasOpenTasks: @js($allOpenUnitTasks->isNotEmpty()) }"
                        :class="{ 'wp-portal-open-tasks-card--attention': hasOpenTasks && !open }"
                    >
                        <button
                            type="button"
                            class="wp-row"
                            data-manual-capture-trigger="unit-open-tasks"
                            style="width:100%;background:none;border:none;padding:0;cursor:pointer;"
                            @click="open = !open"
                            :aria-expanded="open"
                        >
                            <h2 id="portal-open-tasks" class="wp-section-title" style="margin:0;">{{ __('portal.worker.open_tasks_with_count', ['count' => $allOpenUnitTasks->count()]) }}</h2>
                            <x-wp-icon name="chevron-down" class="wp-icon" x-show="!open" />
                            <x-wp-icon name="chevron-up" class="wp-icon" x-show="open" x-cloak />
                        </button>

                        <div x-show="open" x-transition wire:key="portal-open-tasks-content">
                            @forelse ($allOpenUnitTasks as $task)
                                @include('partials.wp-portal-task', ['task' => $task, 'team' => $team, 'worker' => $worker])
                            @empty
                                <p class="wp-muted">{{ __('portal.worker.no_open_tasks') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            @endif
            </div>
        @endif

        {{-- ============================ NEW ============================ --}}
        @if ($portalSection === 'new')
            <div data-manual-capture="portal-unit-new">
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
            </div>
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
                <div
                    class="wp-card wp-card-pad wp-stack"
                    wire:key="issue-open-tasks-card"
                    x-data="{ open: false, hasOpenTasks: @js($openTasksForIssue->isNotEmpty()) }"
                    :class="{ 'wp-portal-open-tasks-card--attention': hasOpenTasks && !open }"
                >
                    <button
                        type="button"
                        class="wp-row"
                        style="width:100%;background:none;border:none;padding:0;cursor:pointer;"
                        @click="open = !open"
                        :aria-expanded="open"
                    >
                        <h2 class="wp-section-title" style="margin:0;">{{ __('portal.worker.open_tasks_with_count', ['count' => $openTasksForIssue->count()]) }}</h2>
                        <x-wp-icon name="chevron-down" class="wp-icon" x-show="!open" />
                        <x-wp-icon name="chevron-up" class="wp-icon" x-show="open" x-cloak />
                    </button>

                    <div x-show="open" x-transition wire:key="issue-open-tasks-content">
                        @forelse ($openTasksForIssue as $task)
                            @include('partials.wp-portal-task', ['task' => $task, 'team' => $team, 'worker' => $worker])
                        @empty
                            <p class="wp-muted">{{ __('portal.worker.no_open_tasks') }}</p>
                        @endforelse
                    </div>
                </div>
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
            <div class="wp-card wp-card-pad wp-stack" x-data="{ open: false }">
                <button
                    type="button"
                    class="wp-row"
                    style="width:100%;background:none;border:none;padding:0;cursor:pointer;"
                    @click="open = !open"
                    :aria-expanded="open"
                >
                    <h2 class="wp-section-title" style="margin:0;">{{ __('portal.unit.background_photo_title') }}</h2>
                    <x-wp-icon name="chevron-down" class="wp-icon" x-show="!open" />
                    <x-wp-icon name="chevron-up" class="wp-icon" x-show="open" x-cloak />
                </button>

                <div x-show="open" x-transition wire:key="unit-bg-photo-content">
                    @if ($unitBackgroundUrl)
                        <div class="wp-photo-grid wp-photo-grid--gallery">
                            <div class="wp-photo-thumb" style="position:relative;">
                                <img src="{{ $unitBackgroundUrl }}" alt="" width="80" height="80" loading="lazy">
                                <button
                                    type="button"
                                    class="wp-photo-remove"
                                    wire:confirm="{{ __('portal.unit.background_photo_delete_confirm') }}"
                                    wire:click="deleteUnitBackgroundPhoto()"
                                >×</button>
                            </div>
                        </div>
                    @else
                        <form
                            x-data="{ isOffline: !navigator.onLine }"
                            x-init="
                                queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.());
                                window.addEventListener('offline', () => isOffline = true);
                                window.addEventListener('online', () => isOffline = false);
                            "
                            @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.uploadBackgroundPhoto()"
                            wire:key="portal-bg-photo-form"
                            class="wp-stack"
                        >
                            @include('partials.wp-issue-photo-upload', [
                                'model' => 'backgroundPhoto',
                                'max' => 1,
                                'preferCamera' => true,
                                'removeMethod' => 'removeBackgroundPhoto',
                                'photoAltKey' => 'portal.unit.background_photo_add',
                                'hintKey' => 'portal.unit.background_photo_hint',
                            ])

                            @error('backgroundPhoto') <p class="wp-error">{{ $message }}</p> @enderror
                            @error('backgroundPhoto.0') <p class="wp-error">{{ $message }}</p> @enderror

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
            </div>
        @endif

        {{-- ==================== QR-LINK PHOTOS (BOTTOM) ==================== --}}
        {{-- Show for workers (to add photos) OR for anyone if photos exist --}}
        @if ($portalSection === 'home' && ($workerBelongsToUnitTeam || $qrLinkPhotos->isNotEmpty()))
            @php
                $portalStoredCount = $qrLinkPhotos->count();
                $portalSlotsLeft = max(0, 4 - $portalStoredCount);
                $portalCanAddMore = $portalSlotsLeft > 0;
            @endphp

            <div class="wp-card wp-card-pad wp-stack" wire:key="qr-photos-card" x-data="{ open: false }">
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

                <div x-show="open" x-transition wire:key="qr-photos-content">
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
                                                class="wp-photo-remove"
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
                        @if ($portalCanAddMore)
                            <form
                                x-data="{ isOffline: !navigator.onLine }"
                                x-init="
                                    queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.());
                                    window.addEventListener('offline', () => isOffline = true);
                                    window.addEventListener('online', () => isOffline = false);
                                "
                                @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.updateUnitPhotos()"
                                wire:key="portal-photo-form-{{ $portalStoredCount }}"
                                class="wp-stack"
                            >
                                @include('partials.wp-issue-photo-upload', [
                                    'model' => 'newPortalPhotos',
                                    'max' => $portalSlotsLeft,
                                    'removeMethod' => 'removeNewPortalPhoto',
                                    'preferCamera' => true,
                                    'hintKey' => 'portal.unit.update_photos_hint',
                                ])

                                @error('newPortalPhotos') <p class="wp-error">{{ $message }}</p> @enderror
                                @error('newPortalPhotos.*') <p class="wp-error">{{ $message }}</p> @enderror

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

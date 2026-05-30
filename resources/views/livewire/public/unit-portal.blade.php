<div class="wp-stack">
    <div class="wp-portal-head">
        <div class="wp-portal-head-top">
            <span class="wp-brand">WinProx</span>
            @include('partials.wp-portal-lang')
        </div>
        <p class="wp-muted">
            @if ($locationName){{ $locationName }} &middot; @endif{{ $unitName }}
        </p>
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
                @if ($issues->isNotEmpty())
                    <button type="button" class="wp-tile" wire:click="openSection('issues')">
                        <span class="wp-tile-title">{{ __('portal.tiles.issues') }}</span>
                        <span class="wp-tile-sub">{{ $issues->count() }}</span>
                    </button>
                @endif
                @if ($announcements->isNotEmpty())
                    <button type="button" class="wp-tile" wire:click="openSection('announcements')">
                        <span class="wp-tile-title">{{ __('portal.tiles.announcements') }}</span>
                        <span class="wp-tile-sub">{{ $announcements->count() }}</span>
                    </button>
                @endif
                @if ($documents->isNotEmpty())
                    <button type="button" class="wp-tile" wire:click="openSection('documents')">
                        <span class="wp-tile-title">{{ __('portal.tiles.documents') }}</span>
                        <span class="wp-tile-sub">{{ $documents->count() }}</span>
                    </button>
                @endif
            </div>

            @if ($isFieldVisitor)
                @if ($canAct)
                    <div class="wp-row">
                        <span class="wp-text-body">{{ __('portal.worker.signed_in_as') }} <strong>{{ $worker?->displayName() }}</strong></span>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="signInAsDifferentWorker">{{ __('portal.worker.sign_out') }}</button>
                    </div>
                    <h2 class="wp-section-title">{{ __('portal.worker.open_tasks') }}</h2>
                    @forelse ($allOpenUnitTasks as $task)
                        @include('partials.wp-portal-task', ['task' => $task])
                    @empty
                        <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.worker.no_open_tasks') }}</p></div>
                    @endforelse
                @else
                    @include('partials.wp-unit-worker-signin')
                @endif
            @endif
        @endif

        {{-- ============================ NEW ============================ --}}
        @if ($portalSection === 'new')
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
            <h1 class="wp-page-title">{{ __('portal.report.title') }}</h1>
            <form x-data
                  @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.submitReport()"
                  class="wp-stack">
                <div class="wp-card wp-card-pad wp-stack">
                    <div class="wp-field">
                        <label class="wp-label" for="description">{{ __('portal.report.description') }}</label>
                        <textarea id="description" class="wp-textarea" wire:model="description" rows="5"
                                  placeholder="{{ __('portal.report.description_placeholder') }}"></textarea>
                        @error('description') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label">{{ __('portal.report.photos.label') }}</label>
                        @include('partials.wp-issue-photo-upload', ['model' => 'photos'])
                        @error('photos.*') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('photos') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-portal-actions">
                    <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled">
                        {{ __('portal.report.submit') }}
                    </button>
                </div>
            </form>
        @endif

        {{-- ============================ ISSUES ============================ --}}
        @if ($portalSection === 'issues')
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
            <h1 class="wp-page-title">{{ __('portal.tiles.issues') }}</h1>
            <div class="wp-list">
                @forelse ($issues as $issue)
                    <button type="button" class="wp-card wp-card-pad wp-issue-link" wire:key="issue-{{ $issue->id }}" wire:click="openIssueDetail({{ $issue->id }})">
                        <div class="wp-cluster">
                            <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
                        </div>
                        @if ($issue->isApproved())
                            <p class="wp-text-body">{{ \Illuminate\Support\Str::limit($issue->description, 100) }}</p>
                        @else
                            <div class="wp-pending-review" data-pending-label="{{ __('portal.pending_review') }}">
                                <p class="wp-text-body">{{ \Illuminate\Support\Str::limit($issue->description, 100) }}</p>
                            </div>
                        @endif
                    </button>
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
                @if ($selectedIssue->isApproved())
                    <p class="wp-text-body">{{ $selectedIssue->description }}</p>
                @else
                    <p class="wp-muted">{{ __('portal.issue.awaiting_review_hint') }}</p>
                    <div class="wp-pending-review" data-pending-label="{{ __('portal.pending_review') }}">
                        <p class="wp-text-body">{{ $selectedIssue->description }}</p>
                    </div>
                @endif

                @if ($selectedIssue->photos->isNotEmpty())
                    @if ($selectedIssue->isApproved())
                        <div class="wp-photo-grid">
                            @foreach ($selectedIssue->photos as $photo)
                                <div class="wp-photo-thumb" wire:key="dp-{{ $photo->id }}">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->path) }}" alt="">
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="wp-pending-review" data-pending-label="{{ __('portal.pending_review') }}">
                            <div class="wp-photo-grid">
                                @foreach ($selectedIssue->photos as $photo)
                                    <div class="wp-photo-thumb" wire:key="dp-{{ $photo->id }}"><span></span></div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            @if ($canAct)
                <h2 class="wp-section-title">{{ __('portal.worker.open_tasks') }}</h2>
                @forelse ($openTasksForIssue as $task)
                    @include('partials.wp-portal-task', ['task' => $task])
                @empty
                    <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('portal.worker.no_open_tasks') }}</p></div>
                @endforelse
            @elseif ($isFieldVisitor)
                @include('partials.wp-unit-worker-signin')
            @endif
        @endif

        {{-- ========================== DOCUMENTS ========================== --}}
        @if ($portalSection === 'documents')
            <button type="button" class="wp-back" wire:click="openSection('home')">&larr; {{ __('portal.back') }}</button>
            <h1 class="wp-page-title">{{ __('portal.tiles.documents') }}</h1>
            <div class="wp-list">
                @forelse ($documents as $document)
                    <div class="wp-card wp-card-pad wp-stack-tight" wire:key="doc-{{ $document->id }}">
                        <p class="wp-doc-title">{{ $document->title }}</p>
                        @if ($document->description)<p class="wp-muted">{{ $document->description }}</p>@endif
                        @if ($document->isPubliclyDownloadable())
                            <a class="btn btn--ghost btn--sm" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank" rel="noopener">
                                {{ __('portal.documents.download') }}
                            </a>
                        @else
                            <span class="wp-chip">{{ __('portal.documents.verification_required') }}</span>
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
            <h1 class="wp-page-title">{{ __('portal.tiles.announcements') }}</h1>
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
    @endif
</div>

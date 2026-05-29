<div class="wp-stack">
    <div class="wp-row">
        <h1 class="wp-page-title">{{ __('issues.list.title') }}</h1>
        <a href="{{ route('issues.create') }}" class="btn btn--primary btn--sm">{{ __('issues.list.new') }}</a>
    </div>

    @forelse ($issues as $issue)
        <div class="wp-card wp-card-pad wp-stack" wire:key="issue-{{ $issue->id }}">
            <div class="wp-row">
                <div class="wp-cluster">
                    <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
                    @if ($issue->location)
                        <span class="wp-muted">{{ $issue->location->name }}</span>
                    @endif
                </div>
                <div class="wp-cluster">
                    @unless ($issue->isApproved())
                        <button type="button" class="btn btn--warning btn--sm" wire:click="approve({{ $issue->id }})">
                            {{ __('issues.approve') }}
                        </button>
                    @endunless
                    <a href="{{ route('issues.show', $issue) }}" class="btn btn--ghost btn--sm">{{ __('issues.list.view') }}</a>
                </div>
            </div>

            @if ($issue->isApproved())
                <p class="wp-text-body">{{ \Illuminate\Support\Str::limit($issue->description, 160) }}</p>
            @else
                <div class="wp-pending-review" data-pending-label="{{ __('issues.pending_review') }}">
                    <p class="wp-text-body">{{ \Illuminate\Support\Str::limit($issue->description, 160) }}</p>
                </div>
            @endif
        </div>
    @empty
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('issues.list.empty') }}</p>
        </div>
    @endforelse
</div>

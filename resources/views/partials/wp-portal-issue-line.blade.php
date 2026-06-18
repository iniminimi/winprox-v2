{{-- Melding (nr …) + omschrijving, datum en uur (worker-portaal). --}}
@php
    $secondary = $secondary ?? false;
    $forWorker = $forWorker ?? false;
@endphp

@if ($issue)
    @php
        $textClass = $secondary ? 'wp-muted' : 'wp-text-body';
        $issueMeta = __('portal.worker.issue_meta', [
            'description' => $issue->localizedDescription(),
            'datetime' => $issue->created_at?->isoFormat('D MMM YYYY, HH:mm') ?? '',
        ]);
    @endphp
    @if ($forWorker || $issue->isApproved())
        <p class="{{ $textClass }}">{{ __('portal.worker.issue_heading', ['nr' => $issue->id]) }}</p>
        <p class="{{ $textClass }}">{{ $issueMeta }}</p>
    @else
        <div class="wp-pending-review" data-pending-label="{{ __('portal.pending_review') }}">
            <p class="{{ $textClass }}">{{ __('portal.worker.issue_heading', ['nr' => $issue->id]) }}</p>
            <p class="{{ $textClass }}">{{ $issueMeta }}</p>
        </div>
    @endif
@endif

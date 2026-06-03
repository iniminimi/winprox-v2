{{-- Meldingsfoto's op QR-portaal: alleen oorspronkelijke melding, alleen als bestand op disk bestaat. --}}
@php
    $wireKeyPrefix = $wireKeyPrefix ?? 'portal-photo';
    $forWorker = $forWorker ?? false;
    $issuePhotos = $issue?->photos
        ?->whereNull('issue_update_id')
        ?->filter(fn ($photo) => $photo->hasPublicFile()) ?? collect();
@endphp

@if ($issue && $issuePhotos->isNotEmpty())
    @if ($forWorker || $issue->isApproved())
        @include('partials.wp-issue-photo-gallery', [
            'photos' => $issuePhotos,
            'wireKeyPrefix' => $wireKeyPrefix,
        ])
    @else
        <div class="wp-pending-review" data-pending-label="{{ __('portal.pending_review') }}">
            <div class="wp-photo-grid">
                @foreach ($issuePhotos as $photo)
                    <div class="wp-photo-thumb" wire:key="{{ $wireKeyPrefix }}-{{ $photo->id }}"><span></span></div>
                @endforeach
            </div>
        </div>
    @endif
@endif

@php
    $audienceIds = $item->worker_id !== null ? [(int) $item->worker_id] : $activeWorkerIds;
@endphp
<button type="button" class="btn btn--ghost btn--sm" wire:click="showOpens({{ $item->id }})">
    {{ __('team.library.opened_count', ['opened' => $item->openedCountAmong($audienceIds), 'total' => count($audienceIds)]) }}
</button>

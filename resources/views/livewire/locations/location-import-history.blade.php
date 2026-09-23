<div>
    @if ($batches->isNotEmpty())
        <x-wp-disclosure-card
            :title="__('locations.locations_import_history.title')"
            :subtitle="__('locations.locations_import_history.hint')"
            :count="$batches->count()"
        >
            <div class="wp-list wp-list--entity-rows">
                @foreach ($batches as $batch)
                    <div class="wp-issue-row" wire:key="location-batch-{{ $batch['batch_id'] }}">
                        <div class="wp-issue-row-link wp-stack-tight">
                            <p class="wp-issue-card-title">
                                {{ $batch['file_name'] ?? __('locations.locations_import_history.unknown_file') }}
                            </p>
                            <p class="wp-issue-card-meta">
                                {{ $batch['created_at']->format('d-m-Y H:i') }}
                                &middot; {{ __('locations.locations_import_history.location_count', ['count' => $batch['location_count']]) }}
                            </p>
                        </div>
                        <div class="wp-issue-row-meta">
                            @if ($batch['can_delete'])
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteLocationImportBatch('{{ $batch['batch_id'] }}')"
                                        wire:confirm="{{ __('locations.locations_import_history.confirm_delete', ['count' => $batch['deletable']]) }}">
                                    {{ __('locations.locations_import_history.delete_button', ['count' => $batch['deletable']]) }}
                                </button>
                            @elseif ($batch['blocked'] > 0)
                                <span class="wp-pill wp-pill--closed">{{ __('locations.locations_import_history.has_content') }}</span>
                            @else
                                <span class="wp-pill wp-pill--closed">{{ __('locations.locations_import_history.deleted') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-wp-disclosure-card>
    @endif
</div>

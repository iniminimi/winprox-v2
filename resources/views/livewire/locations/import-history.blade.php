<div>
    @if ($batches->isNotEmpty())
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('locations.import_history.title') }}</h2>
            <p class="wp-muted">{{ __('locations.import_history.hint') }}</p>

            <div class="wp-list wp-list--entity-rows">
                @foreach ($batches as $batch)
                    <div class="wp-issue-row" wire:key="batch-{{ $batch['batch_id'] }}">
                        <div class="wp-issue-row-link wp-stack-tight">
                            <p class="wp-issue-card-title">
                                {{ $batch['file_name'] ?? __('locations.import_history.unknown_file') }}
                            </p>
                            <p class="wp-issue-card-meta">
                                {{ $batch['created_at']->format('d-m-Y H:i') }}
                                &middot; {{ __('locations.import_history.unit_count', ['count' => $batch['unit_count']]) }}
                            </p>
                        </div>
                        <div class="wp-issue-row-meta">
                            @if ($batch['can_delete'])
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteBatch('{{ $batch['batch_id'] }}')"
                                        wire:confirm="{{ __('locations.import_history.confirm_delete', ['count' => $batch['deletable']]) }}">
                                    {{ __('locations.import_history.delete_button', ['count' => $batch['deletable']]) }}
                                </button>
                            @elseif ($batch['blocked'] > 0)
                                <span class="wp-pill wp-pill--closed">{{ __('locations.import_history.has_issues') }}</span>
                            @else
                                <span class="wp-pill wp-pill--closed">{{ __('locations.import_history.deleted') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

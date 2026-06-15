<div>
    @if ($batches->isNotEmpty())
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('team.import_history.title') }}</h2>
            <p class="wp-muted">{{ __('team.import_history.hint') }}</p>

            <div class="wp-list wp-list--entity-rows">
                @foreach ($batches as $batch)
                    <div class="wp-issue-row" wire:key="batch-{{ $batch['batch_id'] }}">
                        <div class="wp-issue-row-link wp-stack-tight">
                            <p class="wp-issue-card-title">
                                {{ $batch['file_name'] ?? __('team.import_history.unknown_file') }}
                            </p>
                            <p class="wp-issue-card-meta">
                                {{ $batch['created_at']->format('d-m-Y H:i') }}
                                &middot; {{ __('team.import_history.worker_count', ['count' => $batch['worker_count']]) }}
                            </p>
                        </div>
                        <div class="wp-issue-row-meta">
                            @if ($batch['can_delete'])
                                <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="deleteBatch('{{ $batch['batch_id'] }}')"
                                    wire:confirm="{{ __('team.import_history.confirm_delete', ['count' => $batch['deletable']]) }}">
                                    {{ __('team.import_history.delete_button', ['count' => $batch['deletable']]) }}
                                </button>
                            @elseif ($batch['blocked'] > 0)
                                <span class="wp-pill wp-pill--closed">{{ __('team.import_history.has_devices') }}</span>
                            @else
                                <span class="wp-pill wp-pill--closed">{{ __('team.import_history.deleted') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

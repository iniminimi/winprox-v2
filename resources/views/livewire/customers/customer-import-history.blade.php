<div>
    @if ($batches->isNotEmpty())
        <x-wp-disclosure-card
            :title="__('customers.customers_import_history.title')"
            :subtitle="__('customers.customers_import_history.hint')"
            :count="$batches->count()"
        >
            <div class="wp-list wp-list--entity-rows">
                @foreach ($batches as $batch)
                    <div class="wp-issue-row" wire:key="customer-batch-{{ $batch['batch_id'] }}">
                        <div class="wp-issue-row-link wp-stack-tight">
                            <p class="wp-issue-card-title">
                                {{ $batch['file_name'] ?? __('customers.customers_import_history.unknown_file') }}
                            </p>
                            <p class="wp-issue-card-meta">
                                {{ $batch['created_at']->format('d-m-Y H:i') }}
                                &middot; {{ __('customers.customers_import_history.customer_count', ['count' => $batch['customer_count']]) }}
                                &middot; {{ __('customers.customers_import_history.location_count', ['count' => $batch['location_count']]) }}
                            </p>
                        </div>
                        <div class="wp-issue-row-meta">
                            @if ($batch['can_delete'])
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteCustomerImportBatch('{{ $batch['batch_id'] }}')"
                                        wire:confirm="{{ __('customers.customers_import_history.confirm_delete', ['customers' => $batch['deletable_customers'], 'locations' => $batch['deletable_locations']]) }}">
                                    {{ __('customers.customers_import_history.delete_button', ['count' => $batch['deletable_customers'] + $batch['deletable_locations']]) }}
                                </button>
                            @elseif ($batch['blocked'] > 0)
                                <span class="wp-pill wp-pill--closed">{{ __('customers.customers_import_history.has_content') }}</span>
                            @else
                                <span class="wp-pill wp-pill--closed">{{ __('customers.customers_import_history.deleted') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-wp-disclosure-card>
    @endif
</div>

<?php

namespace App\Livewire\Locations;

use App\Actions\Units\DeleteImportBatchAction;
use App\Data\Units\DeleteImportBatchData;
use App\Models\Unit;
use App\Support\Tenancy;
use App\Support\Units\ImportBatchRegistry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ImportHistory extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Unit::class);
    }

    public function deleteBatch(string $batchId, DeleteImportBatchAction $deleteBatch): void
    {
        $this->authorize('create', Unit::class);

        $tenantId = Tenancy::id();
        $summary = ImportBatchRegistry::summary($tenantId, $batchId);

        if (! $summary['can_delete']) {
            session()->flash('error', __('locations.import_history.nothing_deletable'));
            return;
        }

        $dto = new DeleteImportBatchData(importBatchId: $batchId);
        $result = $deleteBatch->handle($dto, $tenantId, (int) auth()->id());

        if ($result['success']) {
            if ($result['preserved_count'] > 0) {
                session()->flash('success', __('locations.import_history.partially_deleted', [
                    'deleted' => $result['deleted_count'],
                    'preserved' => $result['preserved_count'],
                ]));
            } else {
                session()->flash('success', __('locations.import_history.fully_deleted', [
                    'count' => $result['deleted_count'],
                ]));
            }
        } else {
            session()->flash('error', $result['errors'][0] ?? __('locations.import_history.delete_failed'));
        }
    }

    public function render()
    {
        $tenantId = Tenancy::id();
        $batches = ImportBatchRegistry::recentBatchesForTenant($tenantId);

        $summaries = $batches->map(function ($batch) use ($tenantId) {
            return array_merge($batch, ImportBatchRegistry::summary($tenantId, $batch['batch_id']));
        });

        return view('livewire.locations.import-history', [
            'batches' => $summaries,
        ]);
    }
}

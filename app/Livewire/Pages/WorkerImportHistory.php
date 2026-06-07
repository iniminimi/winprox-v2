<?php

namespace App\Livewire\Pages;

use App\Actions\Workers\DeleteWorkerImportBatchAction;
use App\Data\Workers\DeleteWorkerImportBatchData;
use App\Models\Worker;
use App\Support\Tenancy;
use App\Support\Workers\WorkerImportBatchRegistry;
use Livewire\Component;

class WorkerImportHistory extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Worker::class);
    }

    public function deleteBatch(string $batchId, DeleteWorkerImportBatchAction $deleteBatch): void
    {
        $this->authorize('create', \App\Models\InternalTeam::class);

        $tenantId = Tenancy::id();
        $summary = WorkerImportBatchRegistry::summary($tenantId, $batchId);

        if (! $summary['can_delete']) {
            session()->flash('error', __('team.import_history.nothing_deletable'));

            return;
        }

        $dto = new DeleteWorkerImportBatchData(importBatchId: $batchId);
        $result = $deleteBatch->handle($dto, $tenantId, (int) auth()->id());

        if ($result['success']) {
            if ($result['preserved_count'] > 0) {
                session()->flash('success', __('team.import_history.partially_deleted', [
                    'deleted'   => $result['deleted_count'],
                    'preserved' => $result['preserved_count'],
                ]));
            } else {
                session()->flash('success', __('team.import_history.fully_deleted', [
                    'count' => $result['deleted_count'],
                ]));
            }
        } else {
            session()->flash('error', $result['errors'][0] ?? __('team.import_history.delete_failed'));
        }
    }

    public function render()
    {
        $tenantId = Tenancy::id();
        $batches = WorkerImportBatchRegistry::recentBatchesForTenant($tenantId);

        $summaries = $batches->map(function ($batch) use ($tenantId) {
            return array_merge($batch, WorkerImportBatchRegistry::summary($tenantId, $batch['batch_id']));
        });

        return view('livewire.pages.worker-import-history', [
            'batches' => $summaries,
        ]);
    }
}

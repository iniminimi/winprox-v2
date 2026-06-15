<?php

namespace App\Livewire\Pages;

use App\Actions\Workers\DeleteWorkerImportBatchAction;
use App\Data\Workers\DeleteWorkerImportBatchData;
use App\Models\Worker;
use App\Support\Tenancy;
use App\Support\Workers\WorkerImportBatchRegistry;
use Livewire\Attributes\On;
use Livewire\Component;

class WorkerImportHistory extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Worker::class);
    }

    #[On('workers-import-changed')]
    public function refreshImportHistory(): void
    {
    }

    public function deleteBatch(string $batchId, DeleteWorkerImportBatchAction $deleteBatch): void
    {
        $this->authorize('create', \App\Models\InternalTeam::class);

        $tenantId = Tenancy::id();
        $summary = WorkerImportBatchRegistry::summary($tenantId, $batchId);

        if (! $summary['can_delete']) {
            $this->dispatch(
                'workers-import-changed',
                notice: __('team.import_history.nothing_deletable'),
                noticeType: 'error',
            );

            return;
        }

        $dto = new DeleteWorkerImportBatchData(importBatchId: $batchId);
        $result = $deleteBatch->handle($dto, $tenantId, (int) auth()->id());

        if ($result['success']) {
            $teamsDeleted = (int) ($result['deleted_team_count'] ?? 0);

            if ($result['preserved_count'] > 0) {
                $notice = $teamsDeleted > 0
                    ? __('team.import_history.partially_deleted_with_teams', [
                        'deleted'   => $result['deleted_count'],
                        'preserved' => $result['preserved_count'],
                        'teams'     => $teamsDeleted,
                    ])
                    : __('team.import_history.partially_deleted', [
                        'deleted'   => $result['deleted_count'],
                        'preserved' => $result['preserved_count'],
                    ]);
            } else {
                $notice = $teamsDeleted > 0
                    ? __('team.import_history.fully_deleted_with_teams', [
                        'count' => $result['deleted_count'],
                        'teams' => $teamsDeleted,
                    ])
                    : __('team.import_history.fully_deleted', [
                        'count' => $result['deleted_count'],
                    ]);
            }

            $this->dispatch('workers-import-changed', notice: $notice, noticeType: 'success');
        } else {
            $this->dispatch(
                'workers-import-changed',
                notice: $result['errors'][0] ?? __('team.import_history.delete_failed'),
                noticeType: 'error',
            );
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

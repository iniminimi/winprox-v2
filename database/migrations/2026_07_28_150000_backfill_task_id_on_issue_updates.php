<?php

use App\Models\Issue;
use App\Models\IssueUpdate;
use App\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koppel oude afhandelingsupdates zonder task_id aan de enige taak van die melding.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('issue_updates', 'task_id')) {
            return;
        }

        $kinds = ['worker_note', 'worker_photos', 'pause', 'status_reason'];

        Issue::query()
            ->withoutGlobalScopes()
            ->select('issues.id')
            ->whereIn('issues.id', function ($query) {
                $query->select('issue_id')
                    ->from('tasks')
                    ->groupBy('issue_id')
                    ->havingRaw('COUNT(*) = 1');
            })
            ->orderBy('issues.id')
            ->chunkById(100, function ($issues) use ($kinds) {
                foreach ($issues as $issue) {
                    $taskId = Task::query()
                        ->withoutGlobalScopes()
                        ->where('issue_id', $issue->id)
                        ->value('id');

                    if ($taskId === null) {
                        continue;
                    }

                    IssueUpdate::query()
                        ->withoutGlobalScopes()
                        ->where('issue_id', $issue->id)
                        ->whereNull('task_id')
                        ->whereIn('kind', $kinds)
                        ->update(['task_id' => $taskId]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible data repair.
    }
};

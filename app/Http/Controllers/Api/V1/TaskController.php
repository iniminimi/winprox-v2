<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\CompleteTaskAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\StartTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Http\Requests\Tasks\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Issue;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::query()->with('issue')->latest();

        if ($request->filled('status')) {
            $status = TaskStatus::tryFrom((string) $request->query('status'));
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if ($request->filled('internal_team_id')) {
            $query->where('internal_team_id', (int) $request->query('internal_team_id'));
        }

        return $this->paginated(TaskResource::collection($query->paginate(25)));
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load('issue');

        return $this->item(new TaskResource($task));
    }

    public function store(Request $request, CreateTaskAction $create): JsonResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'issue_id' => ['required', 'integer', 'exists:issues,id'],
            'internal_team_id' => ['nullable', 'integer', 'exists:internal_teams,id'],
        ]);

        $issue = Issue::query()->findOrFail($validated['issue_id']);
        $this->authorize('view', $issue);

        $task = $create->handle($issue, $validated['internal_team_id'] ?? null);

        return $this->item(new TaskResource($task->load('issue')), 201);
    }

    public function start(Task $task, StartTaskAction $start): JsonResponse
    {
        $this->authorize('update', $task);

        return $this->item(new TaskResource($start->handle($task)));
    }

    public function start(Task $task, StartTaskAction $start): JsonResponse
    {
        $this->authorize('update', $task);

        return $this->item(new TaskResource($start->handle($task)));
    }

    public function complete(Task $task, CompleteTaskAction $complete): JsonResponse
    {
        $this->authorize('update', $task);

        return $this->item(new TaskResource($complete->handle($task)));
    }

    public function updateStatus(Task $task, Request $request, UpdateTaskStatusAction $update): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(UpdateTaskStatusRequest::ruleSet());
        $status = TaskStatus::from($validated['status']);

        return $this->item(new TaskResource($update->handle($task, $status)));
    }
}

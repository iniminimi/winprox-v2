<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Enums\TaskStatus;
use App\Http\Requests\Issues\CreateIssueRequest;
use App\Http\Resources\IssueResource;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Issue::class);

        $query = Issue::query()->with(['tasks', 'translations'])->latest();

        if ($request->filled('status')) {
            $status = TaskStatus::tryFrom((string) $request->query('status'));
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        if ($request->filled('internal_team_id')) {
            $teamId = (int) $request->query('internal_team_id');
            $query->whereHas('tasks', fn ($q) => $q->where('internal_team_id', $teamId));
        }

        return $this->paginated(IssueResource::collection($query->paginate(25)));
    }

    public function show(Issue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        $issue->load(['tasks', 'translations']);

        return $this->item(new IssueResource($issue));
    }

    public function store(Request $request, CreateIssueAction $create): JsonResponse
    {
        $this->authorize('create', Issue::class);

        $validated = $request->validate((new CreateIssueRequest)->rules());
        $teamIds = $validated['team_ids'] ?? [];
        unset($validated['team_ids']);

        $issue = $create->handle($validated, $teamIds, $request->user()->id);
        $issue->load('tasks');

        return $this->item(new IssueResource($issue), 201);
    }

    public function approve(Request $request, Issue $issue, ApproveIssueAction $approve): JsonResponse
    {
        $this->authorize('approve', $issue);

        $issue = $approve->handle($issue, $request->user());
        $issue->load('tasks');

        return $this->item(new IssueResource($issue));
    }
}

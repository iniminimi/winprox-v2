<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $query = Announcement::query()
            ->with('translations')
            ->where('is_active', true)
            ->latest('published_at');

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->query('location_id'));
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', (int) $request->query('unit_id'));
        }

        return $this->paginated(AnnouncementResource::collection($query->paginate(25)));
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        $announcement->load('translations');

        return $this->item(new AnnouncementResource($announcement));
    }
}

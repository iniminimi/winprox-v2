<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Units\ImportUnitsAction;
use App\Http\Requests\Units\ImportUnitsRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        return $this->paginated(
            UnitResource::collection(
                Unit::query()->with('location')->orderBy('name')->paginate(50)
            )
        );
    }

    public function import(Request $request, ImportUnitsAction $importUnits): JsonResponse
    {
        $this->authorize('create', Unit::class);

        $validated = ImportUnitsRequest::validate($request->all());

        $result = $importUnits->handle($validated['file'], (int) auth()->id());

        if ($result['success']) {
            return $this->success([
                'message' => 'Import successful',
                'count' => $result['count'],
            ]);
        }

        return $this->error([
            'message' => 'Import failed',
            'errors' => $result['errors'],
        ], 422);
    }
}

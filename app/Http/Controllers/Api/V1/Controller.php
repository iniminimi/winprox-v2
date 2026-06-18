<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Dunne basis voor alle /api/v1-controllers. Levert de consistente
 * success-envelope: { "data": ..., "meta": { ... } }. Foutformaten worden
 * centraal in bootstrap/app.php (withExceptions) afgehandeld.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * Eén item of een willekeurige resource teruggeven.
     */
    protected function item(JsonResource $resource, int $status = 200): JsonResponse
    {
        return $resource->response()->setStatusCode($status);
    }

    /**
     * Eenvoudige data-response zonder resource-wrapper.
     *
     * @param  array<string, mixed>  $data
     */
    protected function success(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /**
     * Een gepagineerde lijst teruggeven met paginatie-meta.
     */
    protected function paginated(ResourceCollection $collection): JsonResponse
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $collection->resource;

        return $collection
            ->additional([
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ])
            ->response();
    }
}

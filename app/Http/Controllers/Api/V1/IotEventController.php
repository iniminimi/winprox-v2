<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Iot\IngestIotEventAction;
use App\Data\Iot\IngestIotEventData;
use App\Http\Requests\Iot\IngestIotEventRequest;
use App\Http\Resources\IotEventResource;
use App\Models\IotGateway;
use Illuminate\Http\JsonResponse;

class IotEventController extends Controller
{
    public function store(IngestIotEventRequest $request, IngestIotEventAction $ingest): JsonResponse
    {
        /** @var IotGateway $gateway */
        $gateway = $request->attributes->get('iot_gateway');

        $event = $ingest->handle(
            $gateway,
            IngestIotEventData::fromValidated($request->validated()),
        );

        return $this->item(new IotEventResource($event), 201);
    }
}

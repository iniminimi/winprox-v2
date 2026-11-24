<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sense\RecordSenseReadingAction;
use App\Data\Sense\RecordSenseReadingData;
use App\Http\Requests\Sense\RecordSenseReadingRequest;
use App\Models\SensePoint;
use App\Support\Sense\SenseBands;
use Illuminate\Http\JsonResponse;

class SenseReadingController extends Controller
{
    public function store(RecordSenseReadingRequest $request, RecordSenseReadingAction $record): JsonResponse
    {
        /** @var SensePoint $point */
        $point = $request->attributes->get('sense_point');

        $result = $record->handle($point, RecordSenseReadingData::fromValidated($request->validated()));
        $point->refresh();

        return $this->success([
            'id' => $result['reading']->id,
            'created' => $result['created'],
            'attention' => SenseBands::attentions($point) !== [],
        ], $result['created'] ? 201 : 200);
    }
}

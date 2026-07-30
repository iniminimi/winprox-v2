<?php

declare(strict_types=1);

namespace App\Http\Controllers\Locations;

use App\Actions\Units\BuildUnitQrPackAction;
use App\Http\Requests\Units\DownloadUnitQrPackRequest;
use App\Models\Unit;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class UnitQrPackDownloadController
{
    public function __invoke(
        Unit $unit,
        DownloadUnitQrPackRequest $request,
        BuildUnitQrPackAction $action,
    ): Response|SymfonyResponse {
        Gate::authorize('view', $unit);

        @set_time_limit(120);

        try {
            $pack = $action->handle(
                $unit,
                $request->template(),
                (int) $unit->tenant_id,
                auth()->id() !== null ? (int) auth()->id() : null,
            );
        } catch (InvalidArgumentException $exception) {
            abort(503, __('locations.unit_qr_pack.unavailable'));
        }

        return response()->streamDownload(
            static function () use ($pack): void {
                echo $pack->binary;
            },
            $pack->filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ],
        );
    }
}

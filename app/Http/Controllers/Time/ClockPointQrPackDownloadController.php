<?php

declare(strict_types=1);

namespace App\Http\Controllers\Time;

use App\Actions\Time\BuildClockPointQrPackAction;
use App\Http\Requests\Time\DownloadClockPointQrPackRequest;
use App\Models\ClockPoint;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ClockPointQrPackDownloadController
{
    public function __invoke(
        ClockPoint $clockPoint,
        DownloadClockPointQrPackRequest $request,
        BuildClockPointQrPackAction $action,
    ): Response|SymfonyResponse {
        Gate::authorize('view', $clockPoint);

        @set_time_limit(120);

        try {
            $pack = $action->handle(
                $clockPoint,
                $request->template(),
                (int) $clockPoint->tenant_id,
                auth()->id() !== null ? (int) auth()->id() : null,
            );
        } catch (InvalidArgumentException $exception) {
            abort(503, __('time.clock_points.qr.pack.unavailable'));
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

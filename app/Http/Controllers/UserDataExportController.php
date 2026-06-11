<?php

namespace App\Http\Controllers;

use App\Actions\Gdpr\ExportUserDataAction;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserDataExportController
{
    use AuthorizesRequests;

    public function __invoke(Request $request, ExportUserDataAction $export): StreamedResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;
        abort_unless($tenant instanceof Tenant, 403);
        $this->authorize('exportTenantData', $tenant);

        $payload = $export->handle($user);

        $filename = sprintf(
            'winprox-data-export-%d-%s.json',
            $user->id,
            now()->format('Y-m-d'),
        );

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}

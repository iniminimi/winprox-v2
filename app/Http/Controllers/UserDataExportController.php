<?php

namespace App\Http\Controllers;

use App\Actions\Gdpr\ExportUserDataAction;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

final class UserDataExportController
{
    use AuthorizesRequests;

    public function __invoke(Request $request, ExportUserDataAction $export): BinaryFileResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;
        abort_unless($tenant instanceof Tenant, 403);
        $this->authorize('exportTenantData', $tenant);

        $payload = $export->handle($user);

        $date = now()->format('Y-m-d');
        $jsonFilename = sprintf('winprox-data-export-%d-%s.json', $user->id, $date);
        $zipFilename = sprintf('winprox-data-export-%d-%s.zip', $user->id, $date);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $tempBase = tempnam(sys_get_temp_dir(), 'wp-gdpr-');
        $zipPath = $tempBase.'.zip';
        @unlink($tempBase);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create GDPR export archive.');
        }

        $zip->addFromString($jsonFilename, $json);
        $zip->close();

        return response()->download($zipPath, $zipFilename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }
}

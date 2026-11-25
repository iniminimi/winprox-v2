<?php

namespace App\Http\Controllers\Subscription;

use App\Actions\Retention\OpenTenantTimeArchiveAction;
use App\Models\Tenant;
use App\Models\TenantTimeArchive;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantTimeArchiveDownloadController
{
    public function __invoke(Request $request, TenantTimeArchive $archive, OpenTenantTimeArchiveAction $open): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $tenant = Tenant::query()->find($archive->tenant_id);
        abort_unless($tenant instanceof Tenant, 404);
        Gate::authorize('download', $archive);

        $path = $open->handle($tenant, $user, $archive);
        $name = $archive->kind === TenantTimeArchive::KIND_YEAR
            ? 'winprox-'.$archive->period_key.'.zip'
            : 'winprox-fotos-'.$archive->period_key.'.zip';

        return Storage::disk(TenantTimeArchive::DISK)->download($path, $name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

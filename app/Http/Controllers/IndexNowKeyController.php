<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the IndexNow ownership key at /{key}.txt (fallback when front controller handles all paths).
 */
class IndexNowKeyController extends Controller
{
    public function __invoke(): Response
    {
        $key = trim((string) config('indexnow.key', ''));

        if ($key === '' || ! preg_match('/^[a-f0-9]{8,128}$/i', $key)) {
            abort(404);
        }

        return response($key, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

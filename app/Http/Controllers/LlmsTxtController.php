<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Marketing\BuildLlmsTxtAction;
use Illuminate\Http\Response;

class LlmsTxtController extends Controller
{
    public function __invoke(BuildLlmsTxtAction $build): Response
    {
        return response($build->handle(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

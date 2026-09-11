<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Marketing\BuildLlmsFullTxtAction;
use Illuminate\Http\Response;

class LlmsFullTxtController extends Controller
{
    public function __invoke(BuildLlmsFullTxtAction $build): Response
    {
        return response($build->handle(), 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

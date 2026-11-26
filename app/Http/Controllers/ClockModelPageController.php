<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Marketing\JsonLd;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ClockModelPageController extends Controller
{
    public function show(Request $request): View
    {
        $page = match ($request->route()?->getName()) {
            'clock.sense' => 'clock_sense',
            'clock.trace' => 'trace',
            default => abort(404),
        };

        return view('landings.clock-model', [
            'page' => $page,
            'layoutTitle' => __($page.'.meta_title'),
            'layoutSocialTitle' => __($page.'.social.og_title'),
            'layoutSocialDescription' => __($page.'.social.og_description'),
            'layoutJsonLdGraphs' => [
                JsonLd::organization(),
                JsonLd::softwareApplication(),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Platform\StopSupportViewAction;
use Illuminate\Http\RedirectResponse;

final class StopSupportViewController
{
    public function __invoke(StopSupportViewAction $stop): RedirectResponse
    {
        $stop->handle();

        return redirect()->route('platform.tenants');
    }
}

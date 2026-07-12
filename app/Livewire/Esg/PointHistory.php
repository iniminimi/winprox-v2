<?php

declare(strict_types=1);

namespace App\Livewire\Esg;

use App\Actions\Esg\BuildEsgPointHistoryAction;
use App\Models\EsgMeasurement;
use App\Models\Unit;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class PointHistory extends Component
{
    #[Url(as: 'unit')]
    public ?int $unitId = null;

    #[Url(as: 'indicator')]
    public ?int $indicatorId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', EsgMeasurement::class);

        if ($this->unitId === null) {
            abort(404);
        }

        $unit = Unit::query()->whereKey($this->unitId)->first();
        if ($unit === null) {
            abort(404);
        }

        $this->authorize('view', $unit);
    }

    public function render()
    {
        $history = app(BuildEsgPointHistoryAction::class)->handle(
            (int) Tenancy::id(),
            (int) $this->unitId,
            $this->indicatorId,
        );

        if ($history === null) {
            abort(404);
        }

        return view('livewire.esg.point-history', [
            'history' => $history,
        ]);
    }
}

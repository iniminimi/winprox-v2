<?php

declare(strict_types=1);

namespace App\Livewire\Screens;

use App\Actions\Sense\ApplySensePresetAction;
use App\Actions\Sense\ListSenseRoomsAction;
use App\Actions\Sense\UpdateSenseBandsAction;
use App\Models\Screen;
use App\Models\SensePoint;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SenseCard extends Component
{
    use AuthorizesRequests;
    public int $screenId;

    public string $readingsUrl = '';

    public ?string $bandError = null;

    public function mount(int $screenId): void
    {
        $screen = Screen::query()->findOrFail($screenId);
        $this->authorize('update', $screen);
        $this->screenId = $screen->id;
    }

    public function applyPreset(int $pointId, string $preset, ApplySensePresetAction $apply): void
    {
        $point = $this->point($pointId);
        $apply->handle($point, $preset, (int) Tenancy::id(), (int) auth()->id());
    }

    public function saveBands(int $pointId, string $metric, float $low, ?float $target, float $high, UpdateSenseBandsAction $update): bool
    {
        $point = $this->point($pointId);
        $this->bandError = null;

        try {
            $update->handle($point, [
                'metric' => $metric,
                'low' => $low,
                'target' => $target,
                'high' => $high,
            ], (int) Tenancy::id(), (int) auth()->id());
        } catch (ValidationException $exception) {
            $this->bandError = collect($exception->errors())->flatten()->first();

            return false;
        }

        return true;
    }

    public function render(ListSenseRoomsAction $rooms)
    {
        $point = Screen::query()->findOrFail($this->screenId)->sensePoint;
        if ($point === null) {
            return view('livewire.screens.sense-card', ['room' => null]);
        }

        return view('livewire.screens.sense-card', [
            'room' => $rooms->view($point),
        ]);
    }

    private function point(int $pointId): SensePoint
    {
        $point = SensePoint::query()->where('screen_id', $this->screenId)->findOrFail($pointId);
        $this->authorize('update', $point);

        return $point;
    }
}

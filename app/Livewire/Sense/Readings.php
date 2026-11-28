<?php

declare(strict_types=1);

namespace App\Livewire\Sense;

use App\Actions\Sense\FindSensePointForScreenAction;
use App\Actions\Sense\ListSenseReadingsAction;
use App\Http\Requests\Sense\ListSenseReadingsRequest;
use App\Models\SensePoint;
use App\Support\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Readings extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    public int $sensePointId;

    public string $place = '';

    public ?int $unitId = null;

    public ?int $clockPointId = null;

    public function mount(FindSensePointForScreenAction $find): void
    {
        $this->authorize('viewAny', SensePoint::class);

        [$this->unitId, $this->clockPointId] = ListSenseReadingsRequest::screenIds();

        $ref = $find->handle((int) Tenancy::id(), $this->unitId, $this->clockPointId);
        if ($ref === null) {
            abort(404);
        }

        $this->sensePointId = $ref->sensePointId;
        $this->place = $ref->place;

        if ($this->from === '') {
            $this->from = now()->subDays(6)->toDateString();
        }

        if ($this->until === '') {
            $this->until = now()->toDateString();
        }
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedUntil(): void
    {
        $this->resetPage();
    }

    public function render(ListSenseReadingsAction $list): View
    {
        $readings = null;
        $periodError = null;

        try {
            [$from, $until] = ListSenseReadingsRequest::bounds($this->from, $this->until);
            $readings = $list->handle(
                (int) Tenancy::id(),
                $this->sensePointId,
                $from,
                $until,
                (int) $this->getPage(),
            );
        } catch (ValidationException $exception) {
            $periodError = collect($exception->errors())->flatten()->first();
        }

        return view('livewire.sense.readings', [
            'readings' => $readings,
            'periodError' => is_string($periodError) ? $periodError : null,
            'backUrl' => $this->backUrl(),
            'exportUrl' => $readings instanceof LengthAwarePaginator ? $this->downloadUrl('export') : null,
            'printUrl' => $readings instanceof LengthAwarePaginator ? $this->downloadUrl('print') : null,
        ]);
    }

    private function backUrl(): string
    {
        if ($this->clockPointId !== null) {
            return route('time.clock-displays.pair', $this->clockPointId);
        }

        return route('screens.pair', $this->unitId);
    }

    private function downloadUrl(string $kind): string
    {
        $params = ['from' => $this->from, 'until' => $this->until];

        if ($this->clockPointId !== null) {
            return route('time.clock-displays.sense.'.$kind, ['clockPoint' => $this->clockPointId] + $params);
        }

        return route('screens.sense.'.$kind, ['unit' => $this->unitId] + $params);
    }
}

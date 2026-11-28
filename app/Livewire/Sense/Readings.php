<?php

declare(strict_types=1);

namespace App\Livewire\Sense;

use App\Actions\Sense\ListSenseReadingScreensAction;
use App\Actions\Sense\ListSenseReadingsAction;
use App\Data\Sense\SenseReadingCatalog;
use App\Data\Sense\SenseReadingScreenChoice;
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

    #[Url(as: 'location')]
    public string $locationId = '';

    #[Url(as: 'screen')]
    public string $screenId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    #[Url(as: 'alarms')]
    public bool $alarmsOnly = false;

    public string $place = '';

    public ?int $unitId = null;

    public ?int $clockPointId = null;

    public function mount(ListSenseReadingScreensAction $screens): void
    {
        $this->authorize('viewAny', SensePoint::class);

        $catalog = $screens->handle((int) Tenancy::id());
        $this->select($catalog, true);

        if ($this->from === '') {
            $this->from = now()->subDays(6)->toDateString();
        }

        if ($this->until === '') {
            $this->until = now()->toDateString();
        }
    }

    public function updatedLocationId(string $value): void
    {
        $catalog = app(ListSenseReadingScreensAction::class)->handle((int) Tenancy::id());
        $first = $catalog->firstInLocation((int) $value);
        $this->screenId = $first !== null ? (string) $first->sensePointId : '';
        $this->resetPage();
    }

    public function updatedScreenId(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedUntil(): void
    {
        $this->resetPage();
    }

    public function toggleAlarms(): void
    {
        $this->alarmsOnly = ! $this->alarmsOnly;
        $this->resetPage();
    }

    public function render(ListSenseReadingScreensAction $screens, ListSenseReadingsAction $list): View
    {
        $catalog = $screens->handle((int) Tenancy::id());
        $choice = $this->select($catalog, false);
        $screensHere = array_values(array_filter(
            $catalog->screens,
            fn (SenseReadingScreenChoice $screen): bool => (string) $screen->locationId === $this->locationId,
        ));

        $readings = null;
        $periodError = null;

        if ($choice !== null) {
            try {
                [$from, $until] = ListSenseReadingsRequest::bounds($this->from, $this->until);
                $readings = $list->handle(
                    (int) Tenancy::id(),
                    $choice->sensePointId,
                    $from,
                    $until,
                    (int) $this->getPage(),
                    $this->alarmsOnly,
                );
            } catch (ValidationException $exception) {
                $periodError = collect($exception->errors())->flatten()->first();
            }
        }

        return view('livewire.sense.readings', [
            'locations' => $catalog->locations,
            'screensHere' => $screensHere,
            'readings' => $readings,
            'periodError' => is_string($periodError) ? $periodError : null,
            'alarmsOnly' => $this->alarmsOnly,
            'backUrl' => $this->backUrl(),
            'exportUrl' => $readings instanceof LengthAwarePaginator ? $this->downloadUrl('export') : null,
            'printUrl' => $readings instanceof LengthAwarePaginator ? $this->downloadUrl('print') : null,
        ]);
    }

    private function select(SenseReadingCatalog $catalog, bool $missingIsError): ?SenseReadingScreenChoice
    {
        if ($this->screenId === '') {
            [$unitId, $clockPointId] = ListSenseReadingsRequest::screenIds();
            foreach ($catalog->screens as $screen) {
                if ($unitId !== null && $screen->unitId === $unitId) {
                    $this->screenId = (string) $screen->sensePointId;
                    break;
                }
                if ($clockPointId !== null && $screen->clockPointId === $clockPointId) {
                    $this->screenId = (string) $screen->sensePointId;
                    break;
                }
            }

            if ($missingIsError && ($unitId !== null || $clockPointId !== null) && $this->screenId === '') {
                abort(404);
            }
        }

        $choice = $this->screenId !== '' ? $catalog->find((int) $this->screenId) : null;
        if ($choice === null && $this->screenId !== '' && $missingIsError) {
            abort(404);
        }

        if ($choice === null && $catalog->screens !== []) {
            $choice = $catalog->screens[0];
        }

        if ($choice !== null) {
            $this->locationId = (string) $choice->locationId;
            $this->screenId = (string) $choice->sensePointId;
            $this->place = $choice->name;
            $this->unitId = $choice->unitId;
            $this->clockPointId = $choice->clockPointId;
        }

        return $choice;
    }

    private function backUrl(): ?string
    {
        if ($this->clockPointId !== null) {
            return route('time.clock-displays.pair', $this->clockPointId);
        }

        if ($this->unitId !== null) {
            return route('screens.pair', $this->unitId);
        }

        return null;
    }

    private function downloadUrl(string $kind): string
    {
        $params = array_filter([
            'screen' => $this->screenId,
            'from' => $this->from,
            'until' => $this->until,
            'alarms' => $this->alarmsOnly ? 1 : null,
        ], fn ($value) => $value !== null);

        if ($this->clockPointId !== null) {
            return route('time.clock-displays.sense.'.$kind, ['clockPoint' => $this->clockPointId] + $params);
        }

        return route('screens.sense.'.$kind, ['unit' => $this->unitId] + $params);
    }
}

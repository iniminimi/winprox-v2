<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\CreateIssueAction;
use App\Http\Requests\Issues\CreateIssueRequest;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Unit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Create extends Component
{
    public ?int $location_id = null;

    public ?int $unit_id = null;

    public ?string $reporter_name = null;

    public ?string $reporter_contact = null;

    public string $description = '';

    /** @var array<int, int> */
    public array $team_ids = [];

    public function updatedLocationId(): void
    {
        $this->unit_id = null;
    }

    public function save(CreateIssueAction $createIssue)
    {
        $request = new CreateIssueRequest;

        $validated = $this->validate($request->rules(), $request->messages());

        $issue = $createIssue->handle($validated, $this->team_ids);

        return $this->redirectRoute('issues.show', $issue, navigate: false);
    }

    public function render()
    {
        return view('livewire.issues.create', [
            'locations' => Location::query()->orderBy('name')->get(),
            'units' => $this->location_id
                ? Unit::query()->where('location_id', $this->location_id)->orderBy('name')->get()
                : collect(),
            'teams' => InternalTeam::query()->orderBy('name')->get(),
        ]);
    }
}

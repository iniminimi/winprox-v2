<?php

namespace App\Livewire\Public;

use App\Actions\Public\SubmitReportAction;
use App\Http\Requests\Public\ReportIssueRequest;
use App\Models\Unit;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class Report extends Component
{
    use WithFileUploads;

    public int $unitId;
    public int $tenantId;
    public string $locationName = '';
    public string $unitName = '';

    public ?string $reporter_name = null;
    public ?string $reporter_contact = null;
    public string $description = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    public bool $submitted = false;

    public function mount(string $token): void
    {
        $unit = Unit::withoutGlobalScope('tenant')
            ->with('location')
            ->where('qr_token', $token)
            ->first();

        abort_unless($unit, 404);

        $this->unitId = $unit->id;
        $this->tenantId = $unit->tenant_id;
        $this->unitName = $unit->name;
        $this->locationName = $unit->location?->name ?? '';
    }

    public function booted(): void
    {
        Tenancy::actAs($this->tenantId);
    }

    public function submit(SubmitReportAction $submitReport): void
    {
        $request = new ReportIssueRequest;
        $validated = $this->validate($request->rules(), $request->messages());

        $unit = Unit::findOrFail($this->unitId);
        $submitReport->handle($unit, $validated, $this->photos);

        $this->reset(['reporter_name', 'reporter_contact', 'description', 'photos']);
        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.public.report');
    }
}

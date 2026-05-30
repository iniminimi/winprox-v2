<?php

namespace App\Livewire\Pages;

use App\Actions\Team\UpdateOrganisationAction;
use App\Http\Requests\Team\UpdateOrganisationRequest;
use App\Models\Tenant;
use App\Support\TenantLogoStorage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Settings extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $orgName = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $orgLogo = null;

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        if (! $tenant instanceof Tenant) {
            abort(403);
        }

        $this->authorize('manageOrganisation', $tenant);
        $this->orgName = (string) $tenant->name;
    }

    public function saveOrganisation(UpdateOrganisationAction $updateOrganisation, TenantLogoStorage $logoStorage): void
    {
        $tenant = auth()->user()->tenant;
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $request = new UpdateOrganisationRequest;
        $rules = ['orgName' => $request->rules()['name']];
        if ($this->orgLogo !== null) {
            $rules['orgLogo'] = ['nullable', 'image', 'max:2048'];
        }

        $validated = $this->validate(
            $rules,
            ['orgName.required' => __('settings.errors.organisation_name_required')],
        );

        $payload = ['name' => $validated['orgName']];

        if ($this->orgLogo instanceof UploadedFile) {
            $logoStorage->delete($tenant->logo_path);
            $payload['logo_path'] = $logoStorage->store($this->orgLogo, (int) $tenant->id);
            $this->reset('orgLogo');
        }

        $updateOrganisation->handle($tenant, $payload, (int) auth()->id());

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.pages.settings');
    }
}

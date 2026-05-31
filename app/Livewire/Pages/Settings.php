<?php

namespace App\Livewire\Pages;

use App\Actions\Settings\UpdateUserUiThemeAction;
use App\Actions\Team\UpdateOrganisationAction;
use App\Enums\UiTheme;
use App\Http\Requests\Team\UpdateOrganisationRequest;
use App\Models\Tenant;
use App\Support\Platform\SupportTenantContext;
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

    public string $uiTheme = '';

    public bool $canManageOrganisation = false;

    public bool $showOrgModal = false;

    public function mount(): void
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant, 403);

        $user = auth()->user();
        $this->canManageOrganisation = $user->can('manageOrganisation', $tenant);
        $this->uiTheme = $user->uiThemeEnum()->value;

        if ($this->canManageOrganisation) {
            $this->orgName = (string) $tenant->name;
        }
    }

    public function openOrgModal(): void
    {
        $tenant = $this->resolveTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->authorize('manageOrganisation', $tenant);

        $this->orgName = (string) $tenant->name;
        $this->reset('orgLogo');
        $this->resetErrorBag();
        $this->showOrgModal = true;
    }

    public function closeOrgModal(): void
    {
        $this->showOrgModal = false;
        $this->reset('orgLogo');
        $this->resetErrorBag();
    }

    public function saveOrganisation(UpdateOrganisationAction $updateOrganisation, TenantLogoStorage $logoStorage): void
    {
        $tenant = $this->resolveTenant();
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

        $updated = $updateOrganisation->handle($tenant, $payload, (int) auth()->id());
        $this->orgName = $updated->name;

        $user = auth()->user();
        if ($user !== null && (int) $user->tenant_id === (int) $updated->id) {
            $user->setRelation('tenant', $updated);
        }

        $this->closeOrgModal();
        $this->dispatch('saved');
    }

    public function organisationLogoPreviewUrl(): ?string
    {
        if ($this->orgLogo !== null) {
            return $this->orgLogo->temporaryUrl();
        }

        return $this->resolveTenant()?->fresh()?->logoPublicUrl();
    }

    public function updatedUiTheme(string $value, UpdateUserUiThemeAction $updateUserUiTheme): void
    {
        abort_unless($this->resolveTenant() instanceof Tenant, 403);

        $theme = UiTheme::tryFromString($value);
        $user = auth()->user();

        $updateUserUiTheme->handle($user, $theme, (int) $user->id);

        $this->uiTheme = $theme->value;
        $this->dispatch('ui-theme-changed', theme: $theme->value);
    }

    public function render()
    {
        $tenant = $this->resolveTenant();

        return view('livewire.pages.settings', [
            'themeChoices' => UiTheme::choices(),
            'organisationLogoUrl' => $this->organisationLogoPreviewUrl(),
            'orgDisplayName' => $tenant instanceof Tenant ? (string) $tenant->name : '',
        ]);
    }

    private function resolveTenant(): ?Tenant
    {
        $user = auth()->user();
        if ($user->tenant instanceof Tenant) {
            return $user->tenant;
        }

        if ($user->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return null;
    }
}

<?php

namespace App\Livewire\Public;

use App\Actions\Public\SubmitLocationReportAction;
use App\Livewire\Concerns\SwitchesPortalUiTheme;
use App\Http\Requests\Public\ReportIssueRequest;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Portal\PortalAccess;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class LocationPortal extends Component
{
    use SwitchesPortalUiTheme;
    use WithFileUploads;

    public string $token;
    public int $locationId;
    public int $tenantId;
    public string $locationName = '';

    public string $locale = 'nl';
    public ?string $inactiveReasonKey = null;

    public string $portalSection = 'home';
    public string $description = '';
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    public string $reporter_first_name = '';
    public string $reporter_last_name = '';
    public string $reporter_email = '';

    public string $flashMessage = '';

    public function mount(string $token): void
    {
        $location = Location::withoutGlobalScope('tenant')
            ->with(['tenant', 'units' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->where('location_qr_token', $token)
            ->first();

        abort_unless($location, 404);

        $this->token = $token;
        $this->locationId = $location->id;
        $this->tenantId = $location->tenant_id;
        $this->locationName = $location->name;

        Tenancy::actAs($this->tenantId);

        $this->inactiveReasonKey = PortalAccess::locationPortalInactiveReasonKey($location);
        $this->syncLocaleFromRequest();
    }

    public function booted(): void
    {
        Tenancy::actAs($this->tenantId);
        app()->setLocale($this->locale);
    }

    public function switchLocale(string $locale): void
    {
        if (! in_array($locale, config('locales.supported', []), true)) {
            return;
        }

        session(['locale' => $locale]);
        Cookie::queue('locale', $locale, 60 * 24 * 365);
        $this->locale = $locale;
        app()->setLocale($locale);
    }

    public function openSection(string $section): void
    {
        $this->portalSection = $section;

        if ($section === 'new') {
            $this->dispatch('wp-prepare-photo-inputs');
        }
    }

    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            array_splice($this->photos, $index, 1);
        }
    }

    public function submitReport(SubmitLocationReportAction $submit): void
    {
        if ($this->inactiveReasonKey !== null) {
            return;
        }

        $this->description = trim($this->description);
        $this->reporter_first_name = trim($this->reporter_first_name);
        $this->reporter_last_name = trim($this->reporter_last_name);
        $this->reporter_email = trim($this->reporter_email);

        $this->validate(ReportIssueRequest::portalRules(), ReportIssueRequest::validationMessages());

        $submit->handle(
            $this->location(),
            ReportIssueRequest::issueDataFromInput([
                'description' => $this->description,
                'reporter_first_name' => $this->reporter_first_name,
                'reporter_last_name' => $this->reporter_last_name,
                'reporter_email' => $this->reporter_email,
            ]),
            $this->photos,
        );

        $this->reset(['description', 'photos', 'reporter_first_name', 'reporter_last_name', 'reporter_email']);
        $this->dispatch('wp-clear-photo-previews');
        $this->portalSection = 'home';
        $this->flashMessage = __('portal.report.sent');
    }

    public function render()
    {
        $location = $this->location();

        return view('livewire.public.location-portal', [
            'units' => $location->units,
        ]);
    }

    private function location(): Location
    {
        return Location::withoutGlobalScope('tenant')
            ->with(['units' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->whereKey($this->locationId)
            ->firstOrFail();
    }

    private function syncLocaleFromRequest(): void
    {
        $locale = session('locale') ?? request()->cookie('locale') ?? config('app.locale');
        if (in_array($locale, config('locales.supported', []), true)) {
            $this->locale = $locale;
        }
    }
}

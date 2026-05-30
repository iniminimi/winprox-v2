<?php

namespace App\Livewire\Public;

use App\Actions\Public\SubmitLocationReportAction;
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

        $request = new ReportIssueRequest;
        $this->validate(
            ['description' => $request->rules()['description'], 'photos' => $request->rules()['photos']],
            [
                'description.required' => __('portal.report.errors.description_required'),
                'description.min' => __('portal.report.errors.description_required'),
                'description.max' => __('portal.report.errors.description_max'),
                'photos.max' => __('portal.report.errors.photos_max'),
                'photos.*.image' => __('portal.report.errors.photos_image'),
            ],
        );

        $submit->handle($this->location(), ['description' => $this->description], $this->photos);

        $this->reset(['description', 'photos']);
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

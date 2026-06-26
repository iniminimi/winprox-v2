<?php

namespace App\Livewire\Platform;

use App\Actions\Marketing\GeneratePromoCampaignLettersAction;
use App\Actions\Marketing\ImportPromoCampaignSpreadsheetAction;
use App\Actions\Marketing\QueuePromoCampaignEmailsAction;
use App\Actions\Marketing\SendPromoCampaignEmailAction;
use App\Actions\Marketing\UpdatePromoCampaignAction;
use App\Data\Marketing\UpdatePromoCampaignData;
use App\Http\Requests\Marketing\UpdatePromoCampaignRequest;
use App\Models\PromoCampaign;
use App\Models\User;
use App\Support\Marketing\PromoBaseUrl;
use App\Support\Marketing\PromoCampaignSpreadsheetReader;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class PromoCampaignEdit extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public PromoCampaign $campaign;

    public string $name = '';

    public string $locale = 'nl';

    public string $letterBodyHtml = '';

    public string $emailSubject = '';

    public string $emailBodyHtml = '';

    public string $flowImagePath = '';

    public string $mapName = '';

    public string $mapEmail = '';

    public string $mapStreetAddress = '';

    public string $mapPostalCode = '';

    public string $mapCity = '';

    public $spreadsheet;

    /** @var list<string> */
    public array $detectedHeaders = [];

    public int $delaySeconds = 16;

    public string $overrideTo = '';

    public bool $forceGenerate = false;

    public bool $forceSend = false;

    public ?string $flashMessage = null;

    public string $flashType = 'success';

    public ?string $importNotice = null;

    public string $importNoticeType = 'success';

    public function mount(PromoCampaign $promoCampaign): void
    {
        $this->authorize('managePromoCampaigns', User::class);
        $this->campaign = $promoCampaign;
        $this->fillFromCampaign();
    }

    public function updatedSpreadsheet(): void
    {
        $this->importNotice = null;
        $this->detectedHeaders = [];
        if ($this->spreadsheet === null) {
            return;
        }

        $path = $this->spreadsheet->getRealPath();
        if ($path === false) {
            return;
        }

        try {
            $this->detectedHeaders = app(PromoCampaignSpreadsheetReader::class)->detectedHeaderLabels($path);
        } catch (\Throwable) {
            $this->detectedHeaders = [];
        }
    }

    public function save(UpdatePromoCampaignAction $update): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $this->persistCampaign($update, (int) $user->id);

        $this->flashType = 'success';
        $this->flashMessage = __('platform.promo_campaigns.saved');
    }

    public function importSpreadsheet(ImportPromoCampaignSpreadsheetAction $import): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $this->validate([
            'spreadsheet' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'mapName' => ['required', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        if ($user === null || $this->spreadsheet === null) {
            return;
        }

        $originalFilename = (string) $this->spreadsheet->getClientOriginalName();

        $storedPath = $this->spreadsheet->storeAs(
            'promo-campaigns/'.$this->campaign->slug.'/imports',
            now()->format('Ymd_His').'.xlsx',
            'local',
        );

        $absolutePath = Storage::disk('local')->path($storedPath);

        $result = $import->handle(
            campaign: $this->campaign,
            spreadsheetPath: $absolutePath,
            originalFilename: $originalFilename,
            columnMapping: $this->columnMappingArray(),
            actorUserId: (int) $user->id,
        );

        $targetCount = $result['target_count'];

        $this->spreadsheet = null;
        $this->detectedHeaders = [];
        $this->campaign->refresh();
        $this->fillFromCampaign();

        if ($targetCount > 0) {
            $this->importNoticeType = 'success';
            $this->importNotice = __('platform.promo_campaigns.imported_detail', [
                'count' => $targetCount,
                'filename' => $originalFilename,
            ]);
            $this->flashType = 'success';
            $this->flashMessage = __('platform.promo_campaigns.imported', ['count' => $targetCount]);
        } else {
            $this->importNoticeType = 'error';
            $this->importNotice = __('platform.promo_campaigns.imported_empty', [
                'filename' => $originalFilename,
            ]);
            $this->flashType = 'error';
            $this->flashMessage = $this->importNotice;
        }

        $this->dispatch('promo-campaign-import-done');
    }

    public function generateLetters(
        GeneratePromoCampaignLettersAction $generate,
        UpdatePromoCampaignAction $update,
    ): void {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        if (PromoBaseUrl::isLocalhost(PromoBaseUrl::resolve(null)) && ! app()->environment('testing')) {
            $this->flashType = 'error';
            $this->flashMessage = __('platform.promo_campaigns.localhost_blocked');

            return;
        }

        $this->persistCampaign($update, (int) $user->id);

        $result = $generate->handle(
            campaign: $this->campaign,
            actorUserId: (int) $user->id,
            promoBaseUrl: PromoBaseUrl::resolve(null),
            overwriteExisting: $this->forceGenerate,
        );

        $this->flashType = 'success';
        $this->flashMessage = __('platform.promo_campaigns.generated', [
            'generated' => $result['generated'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function queueEmails(QueuePromoCampaignEmailsAction $queue): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $override = trim($this->overrideTo);

        $result = $queue->handle(
            campaign: $this->campaign,
            actorUserId: (int) $user->id,
            delaySeconds: $this->delaySeconds,
            overrideRecipientEmail: $override !== '' ? $override : null,
            forceResend: $this->forceSend,
        );

        $this->flashType = 'success';
        $this->flashMessage = __('platform.promo_campaigns.queued', [
            'queued' => $result['queued'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function sendTestEmail(SendPromoCampaignEmailAction $send): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $override = trim($this->overrideTo);
        if ($override === '' || filter_var($override, FILTER_VALIDATE_EMAIL) === false) {
            $this->flashType = 'error';
            $this->flashMessage = __('platform.promo_campaigns.test_email_invalid');

            return;
        }

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $target = $this->campaign->targets()->whereNotNull('generated_at')->orderBy('id')->first();
        if ($target === null) {
            $this->flashType = 'error';
            $this->flashMessage = __('platform.promo_campaigns.test_email_no_letter');

            return;
        }

        $send->handle(
            campaign: $this->campaign,
            target: $target->load('promoRecipient'),
            actorUserId: (int) $user->id,
            overrideRecipientEmail: $override,
        );

        $this->flashType = 'success';
        $this->flashMessage = __('platform.promo_campaigns.test_email_sent', ['email' => $override]);
    }

    public function render()
    {
        $flowImages = collect(glob(public_path('images/promo/*')) ?: [])
            ->filter(static fn (string $path): bool => (bool) preg_match('/\.(jpe?g|png)$/i', $path))
            ->map(static fn (string $path): string => 'public/images/promo/'.basename($path))
            ->values()
            ->all();

        $targets = $this->campaign->targets()
            ->with(['latestSentEmailSend', 'latestEmailSend'])
            ->orderBy('name')
            ->limit(50)
            ->get();

        $stats = [
            'targets' => $this->campaign->targets()->count(),
            'generated' => $this->campaign->targets()->whereNotNull('generated_at')->count(),
            'sent' => $this->campaign->emailSends()->where('status', 'sent')->count(),
        ];

        return view('livewire.platform.promo-campaign-edit', [
            'flowImages' => $flowImages,
            'targets' => $targets,
            'stats' => $stats,
            'latestImport' => $this->campaign->imports()->latest('id')->first(),
            'placeholders' => '{{name}}, {{street_address}}, {{postal_code}}, {{city}}, {{email}}, {{promo_url}}',
        ]);
    }

    private function fillFromCampaign(): void
    {
        $mapping = $this->campaign->column_mapping ?? [];

        $this->name = $this->campaign->name;
        $this->locale = $this->campaign->locale;
        $this->letterBodyHtml = (string) ($this->campaign->letter_body_html ?? '');
        $this->emailSubject = (string) ($this->campaign->email_subject ?? '');
        $this->emailBodyHtml = (string) ($this->campaign->email_body_html ?? '');
        $this->flowImagePath = (string) ($this->campaign->flow_image_path ?? '');
        $this->mapName = (string) ($mapping['name'] ?? '');
        $this->mapEmail = (string) ($mapping['email'] ?? '');
        $this->mapStreetAddress = (string) ($mapping['street_address'] ?? '');
        $this->mapPostalCode = (string) ($mapping['postal_code'] ?? '');
        $this->mapCity = (string) ($mapping['city'] ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function columnMappingArray(): array
    {
        return array_filter([
            'name' => $this->mapName,
            'email' => $this->mapEmail,
            'street_address' => $this->mapStreetAddress,
            'postal_code' => $this->mapPostalCode,
            'city' => $this->mapCity,
        ], static fn (string $value): bool => trim($value) !== '');
    }

    private function persistCampaign(UpdatePromoCampaignAction $update, int $actorUserId): void
    {
        $this->validate(UpdatePromoCampaignRequest::ruleSet());

        $update->handle(
            campaign: $this->campaign,
            data: new UpdatePromoCampaignData(
                name: $this->name,
                locale: $this->locale,
                letterBodyHtml: $this->letterBodyHtml !== '' ? $this->letterBodyHtml : null,
                emailSubject: $this->emailSubject !== '' ? $this->emailSubject : null,
                emailBodyHtml: $this->emailBodyHtml !== '' ? $this->emailBodyHtml : null,
                flowImagePath: $this->flowImagePath !== '' ? $this->flowImagePath : null,
                columnMapping: $this->columnMappingArray(),
            ),
            actorUserId: $actorUserId,
        );

        $this->campaign->refresh();
    }
}

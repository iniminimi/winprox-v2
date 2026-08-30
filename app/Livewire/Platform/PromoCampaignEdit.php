<?php

namespace App\Livewire\Platform;

use App\Actions\Marketing\DeletePromoCampaignAction;
use App\Actions\Marketing\GeneratePromoCampaignLettersAction;
use App\Actions\Marketing\ImportPromoCampaignSpreadsheetAction;
use App\Actions\Marketing\PausePromoCampaignSendingAction;
use App\Actions\Marketing\QueuePromoCampaignEmailsAction;
use App\Actions\Marketing\ResumePromoCampaignSendingAction;
use App\Actions\Marketing\SendPromoCampaignEmailAction;
use App\Actions\Marketing\SummarizePromoCampaignsDeliveryAction;
use App\Actions\Marketing\SummarizePromoCampaignVisitStatsAction;
use App\Actions\Marketing\UpdatePromoCampaignAction;
use App\Data\Marketing\UpdatePromoCampaignData;
use App\Enums\PromoLanding;
use App\Enums\PromoEmailsPauseReason;
use App\Http\Requests\Marketing\UpdatePromoCampaignRequest;
use App\Models\PromoCampaign;
use App\Models\User;
use App\Support\Marketing\PromoBaseUrl;
use App\Support\Marketing\PromoCampaignHtmlSanitizer;
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

    public string $landing = 'government';

    public string $letterBodyHtml = '';

    public string $emailSubject = '';

    public string $emailBodyHtml = '';

    public string $flowImagePath = '';

    public string $youtubeUrl = '';

    public string $mapName = '';

    public string $mapEmail = '';

    public string $mapStreetAddress = '';

    public string $mapPostalCode = '';

    public string $mapCity = '';

    public $spreadsheet;

    /** @var list<string> */
    public array $detectedHeaders = [];

    /** @var list<array{name: string, email: string, reason: string}> */
    public array $emailCheckSkipped = [];

    public int $emailCheckKept = 0;

    public int $emailCheckSkippedCount = 0;

    public bool $emailCheckDone = false;

    public int $delaySeconds = 20;

    public string $testEmailTo = '';

    public bool $forceGenerate = false;

    public bool $forceSend = false;

    public bool $showQueueConfirm = false;

    public bool $showPauseConfirm = false;

    public bool $showDeleteConfirm = false;

    public int $queueConfirmQueued = 0;

    public int $queueConfirmSkipped = 0;

    public ?string $noticeMessage = null;

    public string $noticeType = 'success';

    public function dismissNotice(): void
    {
        $this->noticeMessage = null;
    }

    public function dismissQueueConfirm(): void
    {
        $this->showQueueConfirm = false;
    }

    public function dismissPauseConfirm(): void
    {
        $this->showPauseConfirm = false;
    }

    public function openPauseConfirm(): void
    {
        $this->authorize('managePromoCampaigns', User::class);
        $this->showPauseConfirm = true;
    }

    public function confirmPauseSending(PausePromoCampaignSendingAction $pause): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        $result = $pause->handle(
            $this->campaign,
            $user !== null ? (int) $user->id : null,
            PromoEmailsPauseReason::Manual,
        );
        $this->campaign->refresh();
        $this->showPauseConfirm = false;
        $this->showNotice(__('platform.promo_campaigns.paused_notice', [
            'purged' => $result['purged_jobs'],
        ]));
    }

    public function resumeSending(ResumePromoCampaignSendingAction $resume): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        $resume->handle($this->campaign, $user !== null ? (int) $user->id : null);
        $this->campaign->refresh();
        $this->showNotice(__('platform.promo_campaigns.resumed_notice'));
    }

    public function openQueueConfirm(QueuePromoCampaignEmailsAction $queue): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        if (! (bool) config('winprox.promo_campaign_emails_enabled', true)) {
            $this->showNotice(__('platform.promo_campaigns.queue_disabled'), 'error');

            return;
        }

        if ($this->campaign->isEmailSendingPaused()) {
            $this->showNotice(__('platform.promo_campaigns.queue_paused'), 'error');

            return;
        }

        $preview = $queue->preview($this->campaign, $this->forceSend);

        if ($preview['queued'] === 0) {
            $this->showNotice(__('platform.promo_campaigns.queue_none'), 'error');

            return;
        }

        $this->queueConfirmQueued = $preview['queued'];
        $this->queueConfirmSkipped = $preview['skipped'];
        $this->showQueueConfirm = true;
    }

    public function confirmQueueEmails(QueuePromoCampaignEmailsAction $queue): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $this->showQueueConfirm = false;

        try {
            $result = $queue->handle(
                campaign: $this->campaign,
                actorUserId: (int) $user->id,
                delaySeconds: $this->delaySeconds,
                forceResend: $this->forceSend,
            );
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();
            if ($message === QueuePromoCampaignEmailsAction::PAUSED_MESSAGE) {
                $this->showNotice(__('platform.promo_campaigns.queue_paused'), 'error');

                return;
            }
            if ($message === QueuePromoCampaignEmailsAction::DISABLED_MESSAGE) {
                $this->showNotice(__('platform.promo_campaigns.queue_disabled'), 'error');

                return;
            }

            throw $exception;
        }

        $this->showNotice(__('platform.promo_campaigns.queued', [
            'queued' => $result['queued'],
            'skipped' => $result['skipped'],
        ]));
    }

    public function mount(PromoCampaign $promoCampaign): void
    {
        $this->authorize('managePromoCampaigns', User::class);
        $this->campaign = $promoCampaign;
        $this->fillFromCampaign();
    }

    public function updatedSpreadsheet(): void
    {
        $this->noticeMessage = null;
        $this->detectedHeaders = [];
        $this->resetEmailCheck();
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

        $this->dropStaleColumnMappings();
    }

    private function dropStaleColumnMappings(): void
    {
        if ($this->detectedHeaders === []) {
            return;
        }

        $headers = $this->detectedHeaders;
        $clearIfMissing = static function (string $value) use ($headers): string {
            return $value !== '' && ! in_array($value, $headers, true) ? '' : $value;
        };

        $this->mapName = $clearIfMissing($this->mapName);
        $this->mapEmail = $clearIfMissing($this->mapEmail);
        $this->mapStreetAddress = $clearIfMissing($this->mapStreetAddress);
        $this->mapPostalCode = $clearIfMissing($this->mapPostalCode);
        $this->mapCity = $clearIfMissing($this->mapCity);

        if ($this->mapName === '' && in_array('name', $headers, true)) {
            $this->mapName = 'name';
        } elseif ($this->mapName === '' && in_array('naam', $headers, true)) {
            $this->mapName = 'naam';
        }

        if ($this->mapEmail === '' && in_array('email', $headers, true)) {
            $this->mapEmail = 'email';
        } elseif ($this->mapEmail === '' && in_array('e-mail', $headers, true)) {
            $this->mapEmail = 'e-mail';
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

        $this->showNotice(__('platform.promo_campaigns.saved'));
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
        $this->applyEmailScreening(
            (int) $result['emails_kept'],
            (int) $result['emails_skipped'],
            $result['skipped'],
        );

        $this->spreadsheet = null;
        $this->detectedHeaders = [];
        $this->campaign->refresh();
        $this->fillFromCampaign();

        if ($targetCount > 0) {
            if ((int) $result['emails_skipped'] > 0) {
                $this->showNotice(__('platform.promo_campaigns.imported_detail_skipped', [
                    'count' => $targetCount,
                    'filename' => $originalFilename,
                    'kept' => $result['emails_kept'],
                    'skipped' => $result['emails_skipped'],
                ]));
            } else {
                $this->showNotice(__('platform.promo_campaigns.imported_detail', [
                    'count' => $targetCount,
                    'filename' => $originalFilename,
                ]));
            }
        } else {
            $this->showNotice(__('platform.promo_campaigns.imported_empty', [
                'filename' => $originalFilename,
            ]), 'error');
        }
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
            $this->showNotice(__('platform.promo_campaigns.localhost_blocked'), 'error');

            return;
        }

        $this->persistCampaign($update, (int) $user->id);

        $result = $generate->handle(
            campaign: $this->campaign,
            actorUserId: (int) $user->id,
            promoBaseUrl: PromoBaseUrl::resolve(null),
            overwriteExisting: $this->forceGenerate,
        );

        $this->showNotice(__('platform.promo_campaigns.generated', [
            'generated' => $result['generated'],
            'skipped' => $result['skipped'],
        ]));
    }

    public function sendTestEmail(SendPromoCampaignEmailAction $send, UpdatePromoCampaignAction $update): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $testEmail = trim($this->testEmailTo);
        if ($testEmail === '' || filter_var($testEmail, FILTER_VALIDATE_EMAIL) === false) {
            $this->showNotice(__('platform.promo_campaigns.test_email_invalid'), 'error');

            return;
        }

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $this->persistCampaign($update, (int) $user->id);

        $target = $this->campaign->targets()->orderBy('id')->first();

        if ($target === null) {
            $this->showNotice(__('platform.promo_campaigns.test_email_no_target'), 'error');

            return;
        }

        try {
            $send->handle(
                campaign: $this->campaign,
                target: $target->load('promoRecipient'),
                actorUserId: (int) $user->id,
                overrideRecipientEmail: $testEmail,
            );
        } catch (\RuntimeException $exception) {
            $message = match ($exception->getMessage()) {
                'Email subject and body are required.' => __('platform.promo_campaigns.test_email_missing_body'),
                default => __('platform.promo_campaigns.test_email_failed', [
                    'reason' => $exception->getMessage(),
                ]),
            };
            $this->showNotice($message, 'error');

            return;
        }

        $this->showNotice(__('platform.promo_campaigns.test_email_sent', ['email' => $testEmail]));
    }

    private function showNotice(string $message, string $type = 'success'): void
    {
        $this->noticeMessage = $message;
        $this->noticeType = $type;
    }

    public function openDeleteConfirm(): void
    {
        $this->authorize('managePromoCampaigns', User::class);
        $this->showDeleteConfirm = true;
    }

    public function dismissDeleteConfirm(): void
    {
        $this->showDeleteConfirm = false;
    }

    public function deleteCampaign(DeletePromoCampaignAction $delete): void
    {
        $this->authorize('managePromoCampaigns', User::class);

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $delete->handle($this->campaign, (int) $user->id);
        $this->redirect(route('platform.promo-campaigns'), navigate: true);
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
            'bounced' => $this->campaign->targets()->where('undelivered', true)->count(),
        ];

        $delivery = app(SummarizePromoCampaignsDeliveryAction::class)
            ->handle(collect([$this->campaign]))[$this->campaign->id] ?? null;

        $visitStats = app(SummarizePromoCampaignVisitStatsAction::class)->handle($this->campaign);

        return view('livewire.platform.promo-campaign-edit', [
            'flowImages' => $flowImages,
            'targets' => $targets,
            'stats' => $stats,
            'delivery' => $delivery,
            'visitStats' => $visitStats,
            'latestImport' => $this->campaign->imports()->latest('id')->first(),
        ]);
    }

    private function fillFromCampaign(): void
    {
        $mapping = $this->campaign->column_mapping ?? [];

        $this->name = $this->campaign->name;
        $this->locale = $this->campaign->locale;
        $this->landing = $this->campaign->landing->value;
        $this->letterBodyHtml = PromoCampaignHtmlSanitizer::forEditor($this->campaign->letter_body_html);
        $this->emailSubject = (string) ($this->campaign->email_subject ?? '');
        $this->emailBodyHtml = PromoCampaignHtmlSanitizer::forEditor($this->campaign->email_body_html);
        $this->flowImagePath = (string) ($this->campaign->flow_image_path ?? '');
        $this->youtubeUrl = (string) ($this->campaign->youtube_url ?? '');
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

    /**
     * @param  list<\App\Data\Marketing\PromoCampaignSkippedEmailData>  $skipped
     */
    private function applyEmailScreening(int $kept, int $skippedCount, array $skipped): void
    {
        $this->emailCheckKept = $kept;
        $this->emailCheckSkippedCount = $skippedCount;
        $this->emailCheckDone = true;
        $this->emailCheckSkipped = array_map(
            static fn ($item): array => [
                'name' => $item->name,
                'email' => $item->email,
                'reason' => $item->reason->value,
            ],
            $skipped,
        );
    }

    private function resetEmailCheck(): void
    {
        $this->emailCheckSkipped = [];
        $this->emailCheckKept = 0;
        $this->emailCheckSkippedCount = 0;
        $this->emailCheckDone = false;
    }

    private function persistCampaign(UpdatePromoCampaignAction $update, int $actorUserId): void
    {
        $this->validate(UpdatePromoCampaignRequest::ruleSet());

        $update->handle(
            campaign: $this->campaign,
            data: new UpdatePromoCampaignData(
                name: $this->name,
                locale: $this->locale,
                landing: PromoLanding::from($this->landing),
                letterBodyHtml: PromoCampaignHtmlSanitizer::forEditor($this->letterBodyHtml) ?: null,
                emailSubject: $this->emailSubject !== '' ? $this->emailSubject : null,
                emailBodyHtml: PromoCampaignHtmlSanitizer::forEditor($this->emailBodyHtml) ?: null,
                flowImagePath: $this->flowImagePath !== '' ? $this->flowImagePath : null,
                youtubeUrl: $this->youtubeUrl !== '' ? $this->youtubeUrl : null,
                columnMapping: $this->columnMappingArray(),
            ),
            actorUserId: $actorUserId,
        );

        $this->campaign->refresh();
    }
}

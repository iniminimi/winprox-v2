<?php

namespace App\Actions\Time;

use App\Actions\Marketing\AssessPromoCampaignEmailAction;
use App\Data\Time\SendClockPointQrMailData;
use App\Enums\PromoEmailPreflightReason;
use App\Mail\ClockPointQrMail;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\QrCodePngWriter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SendClockPointQrMailAction
{
    public const MAX_PER_WINDOW = 8;

    public const WINDOW_SECONDS = 600;

    public function __construct(
        private AssessPromoCampaignEmailAction $assessEmail,
        private AuditRecorder $audit,
    ) {}

    public static function rateLimitKey(int $tenantId, ?int $actorUserId): string
    {
        return 'clock-point-qr-mail:'.$tenantId.':'.($actorUserId ?? 0);
    }

    public function handle(
        ClockPoint $clockPoint,
        SendClockPointQrMailData $data,
        int $tenantId,
        ?int $actorUserId,
        string $locale,
    ): void {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (! $clockPoint->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('time.clock_points.qr.email.inactive')],
            ]);
        }

        $email = $this->assertRecipientDeliverable($data->email);
        $key = self::rateLimitKey($tenantId, $actorUserId);

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_WINDOW)) {
            throw ValidationException::withMessages([
                'email' => [__('time.clock_points.qr.email.rate_limited', [
                    'seconds' => max(1, RateLimiter::availableIn($key)),
                ])],
            ]);
        }

        if (! QrCodePngWriter::canGenerate()) {
            throw ValidationException::withMessages([
                'email' => [__('time.clock_points.qr.email.qr_unavailable')],
            ]);
        }

        $portalUrl = $clockPoint->portalUrl();
        $qrPng = QrCodePngWriter::writeStringWithWinproxLogo($portalUrl, 240);
        $tenant = Tenant::query()->findOrFail($tenantId);

        Mail::to($email)->send(new ClockPointQrMail(
            tenant: $tenant,
            clockPoint: $clockPoint,
            portalUrl: $portalUrl,
            qrPng: $qrPng,
            mailLocale: $locale,
        ));

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'clock_point.qr_mailed',
            modelType: ClockPoint::class,
            modelId: $clockPoint->id,
            payload: [
                'clock_point_id' => $clockPoint->id,
                'email' => $email,
            ],
        );
    }

    private function assertRecipientDeliverable(string $email): string
    {
        $assessment = $this->assessEmail->handle($email);

        if (! $assessment->hasEmail) {
            throw ValidationException::withMessages([
                'email' => [__('time.clock_points.qr.email.invalid')],
            ]);
        }

        if ($assessment->reason === PromoEmailPreflightReason::PreviouslyBounced) {
            throw ValidationException::withMessages([
                'email' => [__('time.clock_points.qr.email.undeliverable')],
            ]);
        }

        if ($assessment->normalizedEmail === null) {
            throw ValidationException::withMessages([
                'email' => [__('time.clock_points.qr.email.invalid')],
            ]);
        }

        return $assessment->normalizedEmail;
    }
}

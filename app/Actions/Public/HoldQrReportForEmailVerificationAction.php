<?php

namespace App\Actions\Public;

use App\Mail\VerifyQrReportEmailMail;
use App\Models\QrReportEmailHold;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bewaart een QR-melding tot de melder het e-mailadres bevestigt. Geen Issue,
 * taak of IssueCreated tot ConfirmQrReportEmailHoldAction.
 */
class HoldQrReportForEmailVerificationAction
{
    public function __construct(
        private IssuePhotoStorage $storage,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Unit $unit, array $data, array $photos = []): QrReportEmailHold
    {
        $email = trim((string) ($data['reporter_contact'] ?? ''));
        if ($email === '') {
            throw ValidationException::withMessages([
                'reporter_email' => [__('portal.report.errors.reporter_email_required')],
            ]);
        }

        $validPhotos = array_values(array_filter($photos, fn ($photo) => $photo instanceof UploadedFile));
        if ($validPhotos !== []) {
            Tenant::query()->findOrFail($unit->tenant_id)->assertCanAddPhotos(count($validPhotos));
        }

        $photoPaths = [];
        foreach ($validPhotos as $photo) {
            $photoPaths[] = $this->storage->storePrecompressedCopy($photo);
        }

        try {
            $hold = QrReportEmailHold::query()->create([
                'tenant_id' => $unit->tenant_id,
                'unit_id' => $unit->id,
                'description' => $data['description'],
                'reporter_name' => $data['reporter_name'] ?? null,
                'reporter_contact' => $email,
                'original_language' => $data['original_language'] ?? null,
                'photo_paths' => $photoPaths,
                'token' => $this->uniqueToken(),
                'expires_at' => now()->addMinutes(max(1, (int) config('portal.qr_report_email_verification.expire_minutes', 60))),
            ]);
        } catch (\Throwable $e) {
            $this->deleteStoredPhotos($photoPaths);
            throw $e;
        }

        try {
            Mail::to($email)->send(new VerifyQrReportEmailMail($hold->loadMissing('unit.location')));
        } catch (\Throwable $e) {
            $this->deleteStoredPhotos($photoPaths);
            $hold->delete();
            throw $e;
        }

        $this->audit->record(
            userId: null,
            tenantId: (int) $unit->tenant_id,
            action: 'qr_report_email_hold.created',
            modelType: QrReportEmailHold::class,
            modelId: (int) $hold->id,
            payload: [
                'unit_id' => $unit->id,
                'photo_count' => count($photoPaths),
            ],
        );

        return $hold;
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (QrReportEmailHold::withoutGlobalScopes()->where('token', $token)->exists());

        return $token;
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteStoredPhotos(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}

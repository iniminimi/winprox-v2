<?php

namespace App\Actions\Team;

use App\Mail\Team\TeamQrMail;
use App\Models\InternalTeam;
use App\Support\Audit\AuditRecorder;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use Illuminate\Support\Facades\Mail;

class SendTeamQrEmailAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        InternalTeam $team,
        string $portalUrl,
        string $recipientEmail,
        ?int $actorUserId = null,
        string $recipientName = '',
        ?string $locale = null,
    ): void {
        $team->loadMissing('tenant');

        $centerLogoPath = QrCenterLogo::tenantLogoAbsolutePath($team->tenant)
            ?? QrCenterLogo::winproxAbsolutePath();

        $pngBytes = QrCodePngWriter::writeStringWithCenterLogo(
            $portalUrl,
            320,
            null,
            $centerLogoPath,
        );

        Mail::to($recipientEmail, $recipientName !== '' ? $recipientName : null)->send(new TeamQrMail(
            team: $team,
            portalUrl: $portalUrl,
            qrPngBytes: $pngBytes,
            recipientName: $recipientName,
            senderLocale: $locale,
        ));

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $team->tenant_id,
            action: 'team.qr_email_sent',
            modelType: InternalTeam::class,
            modelId: (int) $team->id,
            payload: [
                'team_id' => (int) $team->id,
                'team_name' => $team->name,
                'recipient_email' => $recipientEmail,
            ],
        );
    }
}

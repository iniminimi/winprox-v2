<?php

namespace App\Actions\Team;

use App\Mail\Contact\NewOutboundMessageMail;
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

        $bodyHtml = view('emails.team.qr-body', [
            'intro' => __('team.qr.email_body', ['team' => $team->name]),
            'qrImageDataUri' => 'data:image/png;base64,'.base64_encode($pngBytes),
            'openUrl' => $portalUrl,
            'openLinkLabel' => __('team.qr.open_link'),
        ])->render();

        Mail::to($recipientEmail, $recipientName !== '' ? $recipientName : null)->send(new NewOutboundMessageMail(
            subjectText: __('team.qr.email_subject', ['team' => $team->name]),
            bodyText: '',
            recipientName: $recipientName,
            tenant: $team->tenant,
            bodyHtml: $bodyHtml,
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

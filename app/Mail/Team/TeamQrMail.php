<?php

namespace App\Mail\Team;

use App\Models\InternalTeam;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamQrMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InternalTeam $team,
        public string $portalUrl,
        public string $qrPngBytes,
        public string $recipientName = '',
        public ?string $locale = null,
    ) {
        $this->locale($locale ?? app()->getLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('team.qr.email_subject', ['team' => $this->team->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'bodyText' => '',
                'bodyHtml' => view('emails.team.qr-body', [
                    'intro' => __('team.qr.email_body', ['team' => $this->team->name]),
                    'qrImageDataUri' => 'data:image/png;base64,'.base64_encode($this->qrPngBytes),
                    'openUrl' => $this->portalUrl,
                    'openLinkLabel' => __('team.qr.open_link'),
                ])->render(),
                'recipientName' => $this->recipientName,
                'tenantName' => $this->team->tenant?->name ?? config('app.name'),
            ],
        );
    }
}

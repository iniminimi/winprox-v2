<?php

namespace App\Mail;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class NewQrIssueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Issue $issue,
    ) {
        $locale = in_array((string) $user->locale, config('locales.supported', []), true)
            ? (string) $user->locale
            : (string) config('locales.default', 'nl');

        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        $tenantName = (string) ($this->issue->tenant?->name ?? config('app.name'));

        return new Envelope(
            subject: __('mail.new_qr_issue.subject', ['tenant' => $tenantName]),
        );
    }

    public function content(): Content
    {
        $location = $this->issue->location;
        $locationLine = collect([$location?->name, $this->issue->unit?->name])->filter()->join(' · ');
        $address = $location?->formattedAddress() ?? '';
        $description = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $this->issue->description))), 500);
        $reporterName = trim((string) ($this->issue->reporter_name ?? ''));
        $tenantName = (string) ($this->issue->tenant?->name ?? config('app.name'));
        $issueUrl = URL::route('issues.show', ['issue' => $this->issue->id], true);

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'bodyText' => '',
                'bodyHtml' => view('emails.issues.new-qr-body', [
                    'tenantName' => $tenantName,
                    'locationLine' => $locationLine,
                    'address' => $address,
                    'description' => $description,
                    'reporterName' => $reporterName,
                    'issueUrl' => $issueUrl,
                ])->render(),
                'recipientName' => (string) $this->user->name,
                'tenantName' => $tenantName,
            ],
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email as SymfonyEmail;

class DemoRequestOpsMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array{company: string, name: string, email: string, phone: string, municipality: string, preference: string, note?: ?string}  $data
     */
    public function __construct(
        public array $data,
        public string $preferenceLabel,
    ) {
        $this->withSymfonyMessage(function (SymfonyEmail $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.demo_request.ops_subject', ['company' => $this->data['company']]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.demo-request-ops',
            with: [
                'data' => $this->data,
                'preferenceLabel' => $this->preferenceLabel,
            ],
        );
    }
}

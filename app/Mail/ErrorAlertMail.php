<?php

namespace App\Mail;

use App\Support\Errors\ErrorReference;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;
use Throwable;

/** Interne foutmelding naar WinProx (info@); de stack trace blijft in het serverlog. */
class ErrorAlertMail extends Mailable
{
    use Queueable;

    private const MESSAGE_LIMIT = 2000;

    public string $reference;

    public string $exceptionClass;

    public string $exceptionMessage;

    public string $location;

    public ?string $appLocation;

    /**
     * @param  array<string, int|string>  $context
     */
    public function __construct(Throwable $e, public array $context, public int $repeated = 0)
    {
        $this->locale((string) config('locales.default', 'nl'));
        $this->reference = (string) ($context['error_reference'] ?? '—');
        $this->exceptionClass = $e::class;
        $this->exceptionMessage = Str::limit($e->getMessage(), self::MESSAGE_LIMIT);
        $this->location = Str::after($e->getFile(), base_path().DIRECTORY_SEPARATOR).':'.$e->getLine();
        $appLocation = ErrorReference::appFrame($e);
        $this->appLocation = $appLocation !== $this->location ? $appLocation : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.error_alert.subject', ['reference' => $this->reference]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.error-alert',
            with: [
                'occurredAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i:s'),
                'timezone' => (string) config('app.timezone'),
                'appUrl' => (string) config('app.url'),
            ],
        );
    }
}

<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ReservationConfirmMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public bool $alreadyConfirmed = false,
    ) {
        $this->locale((string) config('locales.default', 'nl'));
        $this->reservation->loadMissing(['unit.location', 'tenant']);
        $this->withSymfonyMessage(function (\Symfony\Component\Mime\Email $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
        });
    }

    public function envelope(): Envelope
    {
        $key = $this->alreadyConfirmed
            ? 'mail.reservation_confirm.subject_confirmed'
            : 'mail.reservation_confirm.subject';

        return new Envelope(subject: __($key));
    }

    public function content(): Content
    {
        $confirmUrl = URL::route('reservations.confirm', ['token' => $this->reservation->confirm_token], true);
        $manageUrl = URL::route('reservations.manage', ['token' => $this->reservation->manage_token], true);
        $unitName = (string) ($this->reservation->unit?->name ?? '');
        $locationName = (string) ($this->reservation->unit?->location?->name ?? '');
        $when = $this->reservation->start_at?->timezone(config('app.timezone'))->format('d-m-Y H:i')
            .' – '.$this->reservation->end_at?->timezone(config('app.timezone'))->format('H:i');

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => null,
                'bodyText' => '',
                'bodyHtml' => view('emails.reservations.confirm-body', [
                    'alreadyConfirmed' => $this->alreadyConfirmed,
                    'guestName' => $this->reservation->guestFullName(),
                    'unitName' => $unitName,
                    'locationName' => $locationName,
                    'when' => $when,
                    'confirmUrl' => $confirmUrl,
                    'manageUrl' => $manageUrl,
                    'holdMinutes' => Reservation::HOLD_MINUTES,
                ])->render(),
            ],
        );
    }
}

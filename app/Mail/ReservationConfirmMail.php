<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ReservationConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public bool $alreadyConfirmed = false,
    ) {
        $this->locale((string) config('locales.default', 'nl'));
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
        $this->reservation->loadMissing(['unit.location', 'tenant']);

        $confirmUrl = URL::route('reservations.confirm', ['token' => $this->reservation->confirm_token], true);
        $manageUrl = URL::route('reservations.manage', ['token' => $this->reservation->manage_token], true);
        $unitName = (string) ($this->reservation->unit?->name ?? '');
        $locationName = (string) ($this->reservation->unit?->location?->name ?? '');
        $when = $this->reservation->start_at?->timezone(config('app.timezone'))->format('d-m-Y H:i')
            .' – '.$this->reservation->end_at?->timezone(config('app.timezone'))->format('H:i');

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
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

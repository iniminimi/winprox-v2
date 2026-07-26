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

class ReservationManageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation)
    {
        $this->locale((string) config('locales.default', 'nl'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.reservation_manage.subject'));
    }

    public function content(): Content
    {
        $this->reservation->loadMissing(['unit.location']);

        $manageUrl = URL::route('reservations.manage', ['token' => $this->reservation->manage_token], true);
        $when = $this->reservation->start_at?->timezone(config('app.timezone'))->format('d-m-Y H:i')
            .' – '.$this->reservation->end_at?->timezone(config('app.timezone'))->format('H:i');

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'bodyText' => '',
                'bodyHtml' => view('emails.reservations.manage-body', [
                    'guestName' => $this->reservation->guestFullName(),
                    'unitName' => (string) ($this->reservation->unit?->name ?? ''),
                    'locationName' => (string) ($this->reservation->unit?->location?->name ?? ''),
                    'when' => $when,
                    'manageUrl' => $manageUrl,
                ])->render(),
            ],
        );
    }
}

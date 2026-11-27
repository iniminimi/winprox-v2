<?php

namespace App\Listeners;

use App\Actions\Auth\RecordBackofficeLoginAction;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Zet het begin van de twaalf uur en noteert de aanmelding.
 */
class RecordBackofficeLogin
{
    public function __construct(private RecordBackofficeLoginAction $record) {}

    public function handle(Login $event): void
    {
        session()->put('auth_logged_in_at', now()->timestamp);

        if ($event->user instanceof User) {
            $this->record->handle($event->user);
        }
    }
}

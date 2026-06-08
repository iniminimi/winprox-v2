<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OutgoingMailBlockedByUnsubscribe
{
    use Dispatchable;

    /**
     * @param  list<string>  $unsubscribedAddresses
     */
    public function __construct(
        public array $unsubscribedAddresses,
        public ?string $subject = null,
    ) {}
}

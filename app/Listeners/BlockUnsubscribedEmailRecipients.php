<?php

namespace App\Listeners;

use App\Events\OutgoingMailBlockedByUnsubscribe;
use App\Models\EmailUnsubscribe;
use App\Support\EmailUnsubscribeExemptions;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BlockUnsubscribedEmailRecipients
{
    /**
     * @return bool|null false = e-mail niet verzenden (MessageSending halt)
     */
    public function handle(MessageSending $event): ?bool
    {
        $blocked = [];

        foreach ($this->nonExemptRecipientEmails($event->message) as $email) {
            if (EmailUnsubscribe::isUnsubscribed($email)) {
                $blocked[] = $email;
            }
        }

        if ($blocked === []) {
            return null;
        }

        event(new OutgoingMailBlockedByUnsubscribe(
            unsubscribedAddresses: array_values(array_unique($blocked)),
            subject: $event->message->getSubject() ?: null,
        ));

        return false;
    }

    /** @return list<string> */
    private function nonExemptRecipientEmails(Email $message): array
    {
        $addresses = [];

        foreach (array_merge($message->getTo(), $message->getCc(), $message->getBcc()) as $addr) {
            if (! $addr instanceof Address) {
                continue;
            }

            $email = EmailUnsubscribe::normalizeEmail($addr->getAddress());

            if (! EmailUnsubscribeExemptions::isExempt($email)) {
                $addresses[] = $email;
            }
        }

        return array_values(array_unique($addresses));
    }
}

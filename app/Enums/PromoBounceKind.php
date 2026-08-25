<?php

declare(strict_types=1);

namespace App\Enums;

enum PromoBounceKind: string
{
    case Blacklist = 'blacklist';
    case Unknown = 'unknown';
    case MailboxFull = 'mailbox_full';
    case Spam = 'spam';
    case Other = 'other';

    public function storagePrefix(): string
    {
        return '['.$this->value.']';
    }

    public static function fromStoredReason(?string $reason): self
    {
        $reason = strtolower(trim((string) $reason));
        foreach (self::cases() as $kind) {
            if (str_starts_with($reason, $kind->storagePrefix())) {
                return $kind;
            }
        }

        return self::Other;
    }
}

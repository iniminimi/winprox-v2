<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Models\EmailUnsubscribe;
use App\Support\Marketing\PromoBounceMessageParser;

class PurgeBogusEmailUnsubscribesAction
{
    public function __construct(
        private LogAuditAction $logAudit,
    ) {}

    /**
     * Remove unsubscribe rows that are Message-IDs / system addresses, not real recipients.
     *
     * @return array{scanned: int, purged: int, emails: list<string>}
     */
    public function handle(bool $dryRun = false): array
    {
        $scanned = 0;
        $purged = 0;
        $emails = [];

        EmailUnsubscribe::query()
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dryRun, &$scanned, &$purged, &$emails): void {
                foreach ($rows as $row) {
                    $scanned++;
                    $email = (string) $row->email;
                    if (PromoBounceMessageParser::isPlausibleRecipientEmail($email)) {
                        continue;
                    }

                    $emails[] = $email;
                    $purged++;

                    if ($dryRun) {
                        continue;
                    }

                    $this->logAudit->handle(
                        userId: null,
                        tenantId: null,
                        action: 'contact.email_unsubscribe_purged_bogus',
                        modelType: 'EmailUnsubscribe',
                        modelId: (int) $row->id,
                        payload: [
                            'email' => $email,
                            'reason' => 'message_id_or_system_address',
                        ],
                    );

                    $row->delete();
                }
            });

        return [
            'scanned' => $scanned,
            'purged' => $purged,
            'emails' => $emails,
        ];
    }
}

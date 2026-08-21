<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Data\Marketing\PromoCampaignEmailScreeningData;
use App\Data\Marketing\PromoCampaignSkippedEmailData;
use App\Enums\PromoEmailPreflightReason;
use App\Support\Marketing\PromoCampaignSpreadsheetReader;

class ScreenPromoCampaignSpreadsheetEmailsAction
{
    public function __construct(
        private PromoCampaignSpreadsheetReader $reader,
        private AssessPromoCampaignEmailAction $assessEmail,
    ) {}

    /**
     * @param  array<string, string>  $columnMapping
     */
    public function handle(string $spreadsheetPath, array $columnMapping): PromoCampaignEmailScreeningData
    {
        @set_time_limit(120);

        $rows = $this->reader->readRows($spreadsheetPath, $columnMapping);
        $emailsKept = 0;
        $emailsSkipped = 0;
        /** @var list<PromoCampaignSkippedEmailData> $skipped */
        $skipped = [];

        foreach ($rows as $index => $row) {
            $email = trim((string) ($row['email'] ?? ''));
            $assessment = $this->assessEmail->handle($email === '' ? null : $email);

            if (! $assessment->hasEmail) {
                $rows[$index]['email'] = '';

                continue;
            }

            if ($assessment->accepted) {
                $rows[$index]['email'] = (string) $assessment->normalizedEmail;
                $emailsKept++;

                continue;
            }

            $skipped[] = new PromoCampaignSkippedEmailData(
                name: (string) ($row['name'] ?? ''),
                email: $email,
                reason: $assessment->reason ?? PromoEmailPreflightReason::InvalidSyntax,
            );
            $rows[$index]['email'] = '';
            $emailsSkipped++;
        }

        return new PromoCampaignEmailScreeningData(
            rows: $rows,
            emailsKept: $emailsKept,
            emailsSkipped: $emailsSkipped,
            skipped: $skipped,
        );
    }
}

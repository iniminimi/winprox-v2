<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Data\Marketing\PromoCampaignSkippedEmailData;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignImport;
use App\Models\PromoCampaignTarget;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportPromoCampaignSpreadsheetAction
{
    public function __construct(
        private ScreenPromoCampaignSpreadsheetEmailsAction $screenEmails,
        private LogAuditAction $logAudit,
    ) {}

    /**
     * @param  array<string, string>  $columnMapping
     * @return array{
     *     import: PromoCampaignImport,
     *     target_count: int,
     *     emails_kept: int,
     *     emails_skipped: int,
     *     skipped: list<PromoCampaignSkippedEmailData>
     * }
     */
    public function handle(
        PromoCampaign $campaign,
        string $spreadsheetPath,
        string $originalFilename,
        array $columnMapping,
        int $actorUserId,
    ): array {
        $columnMapping = array_filter(
            $columnMapping,
            static fn (string $value, string $key): bool => $key === 'name' || trim($value) !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        if (! isset($columnMapping['name']) || trim($columnMapping['name']) === '') {
            throw new RuntimeException('Column mapping must include name.');
        }

        $screening = $this->screenEmails->handle($spreadsheetPath, $columnMapping);
        $rows = $screening->rows;

        $result = DB::transaction(function () use ($campaign, $rows, $originalFilename, $columnMapping, $actorUserId, $screening): array {
            PromoCampaignTarget::query()
                ->where('promo_campaign_id', $campaign->id)
                ->delete();

            $import = PromoCampaignImport::query()->create([
                'promo_campaign_id' => $campaign->id,
                'original_filename' => $originalFilename,
                'row_count' => count($rows),
                'imported_by' => $actorUserId,
                'imported_at' => now(),
            ]);

            foreach ($rows as $row) {
                PromoCampaignTarget::query()->create([
                    'promo_campaign_id' => $campaign->id,
                    'promo_campaign_import_id' => $import->id,
                    'name' => $row['name'],
                    'email' => $this->nullable($row['email'] ?? null),
                    'street_address' => $this->nullable($row['street_address'] ?? null),
                    'postal_code' => $this->nullable($row['postal_code'] ?? null),
                    'city' => $this->nullable($row['city'] ?? null),
                ]);
            }

            $campaign->update([
                'column_mapping' => $columnMapping,
            ]);

            return [
                'import' => $import,
                'target_count' => count($rows),
                'emails_kept' => $screening->emailsKept,
                'emails_skipped' => $screening->emailsSkipped,
                'skipped' => $screening->skipped,
            ];
        });

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_campaign_imported',
            modelType: 'PromoCampaignImport',
            modelId: $result['import']->id,
            payload: [
                'promo_campaign_id' => $campaign->id,
                'slug' => $campaign->slug,
                'target_count' => $result['target_count'],
                'emails_kept' => $result['emails_kept'],
                'emails_skipped' => $result['emails_skipped'],
                'original_filename' => $originalFilename,
            ],
        );

        return $result;
    }

    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

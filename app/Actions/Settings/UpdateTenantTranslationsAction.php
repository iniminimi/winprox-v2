<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Actions\Communication\BackfillAnnouncementTranslationSlotsAction;
use App\Actions\Communication\BackfillCategoryTranslationSlotsAction;
use App\Actions\Communication\BackfillDocumentTranslationSlotsAction;
use App\Actions\Communication\BackfillEsgIndicatorTranslationSlotsAction;
use App\Actions\Communication\BackfillInternalTeamTranslationSlotsAction;
use App\Actions\Communication\BackfillIssueTranslationSlotsAction;
use App\Actions\Communication\BackfillLocationTranslationSlotsAction;
use App\Actions\Communication\BackfillTaskTranslationSlotsAction;
use App\Actions\Communication\BackfillUnitCheckListTranslationSlotsAction;
use App\Actions\Communication\BackfillUnitTranslationSlotsAction;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

/** Zet automatische vertaling aan of uit voor de hele organisatie. */
final class UpdateTenantTranslationsAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, bool $enabled, int $actorUserId): Tenant
    {
        $tenant->update(['translations_enabled' => $enabled]);
        $fresh = $tenant->refresh();

        if ($enabled) {
            $tenantId = (int) $fresh->id;
            app(BackfillIssueTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillTaskTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillAnnouncementTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillDocumentTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillUnitTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillLocationTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillCategoryTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillInternalTeamTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillEsgIndicatorTranslationSlotsAction::class)->handle($tenantId);
            app(BackfillUnitCheckListTranslationSlotsAction::class)->handle($tenantId);
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.translations_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: ['translations_enabled' => $enabled],
        );

        return $fresh;
    }
}

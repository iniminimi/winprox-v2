<?php

namespace App\Actions\Marketing;

use App\Actions\Audit\LogAuditAction;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientToken;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreatePromoRecipientAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    public function handle(string $label, ?string $note, int $actorUserId): PromoRecipient
    {
        $label = trim($label);
        $note = $note !== null ? trim($note) : null;
        if ($note === '') {
            $note = null;
        }

        $recipient = DB::transaction(function () use ($label, $note, $actorUserId): PromoRecipient {
            for ($i = 0; $i < 64; $i++) {
                $token = PromoRecipientToken::generate();
                if (PromoRecipient::query()->where('token', $token)->exists()) {
                    continue;
                }

                return PromoRecipient::query()->create([
                    'token' => $token,
                    'label' => $label,
                    'note' => $note,
                    'created_by' => $actorUserId,
                ]);
            }

            throw new RuntimeException('Could not create promo recipient token.');
        });

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'marketing.promo_recipient_created',
            payload: [
                'promo_recipient_id' => $recipient->id,
                'token' => $recipient->token,
                'label' => $recipient->label,
            ],
        );

        return $recipient;
    }
}

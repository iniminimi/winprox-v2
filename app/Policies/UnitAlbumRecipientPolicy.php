<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumRecipient;
use App\Models\User;
use App\Support\Platform\SuperuserTenantAccess;

class UnitAlbumRecipientPolicy
{
    public function update(User $user, UnitAlbumRecipient $recipient): bool
    {
        $recipient->loadMissing('unit');

        return $user->can('viewAny', UnitAlbumPhoto::class)
            && SuperuserTenantAccess::canAccessTenant($user, (int) $recipient->tenant_id)
            && $recipient->unit !== null
            && $user->can('view', $recipient->unit);
    }

    public function delete(User $user, UnitAlbumRecipient $recipient): bool
    {
        return $this->update($user, $recipient);
    }
}

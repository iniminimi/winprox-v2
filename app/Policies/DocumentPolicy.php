<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Location;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return (new LocationPolicy)->viewAny($user);
    }

    public function create(User $user, Location $location): bool
    {
        return (new LocationPolicy)->update($user, $location);
    }

    public function update(User $user, Document $document): bool
    {
        $document->loadMissing('location');

        if ($document->location === null) {
            return false;
        }

        return $user->is_superuser
            || ((int) $user->tenant_id === (int) $document->tenant_id
                && (new LocationPolicy)->update($user, $document->location));
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}

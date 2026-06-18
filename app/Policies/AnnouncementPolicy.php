<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\Location;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return (new LocationPolicy)->viewAny($user);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->is_superuser
            || (int) $user->tenant_id === (int) $announcement->tenant_id;
    }

    public function create(User $user, Location $location): bool
    {
        return (new LocationPolicy)->update($user, $location);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        $announcement->loadMissing('location');

        if ($announcement->location === null) {
            return false;
        }

        return $user->is_superuser
            || ((int) $user->tenant_id === (int) $announcement->tenant_id
                && (new LocationPolicy)->update($user, $announcement->location));
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}

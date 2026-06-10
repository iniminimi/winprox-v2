<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContactMessagePolicy
{
    public function viewAny(User $user): Response
    {
        return $this->allowPlatformUser($user);
    }

    public function view(User $user, ContactMessage $contactMessage): Response
    {
        return $this->allowPlatformUser($user);
    }

    public function create(User $user): Response
    {
        return $this->allowPlatformUser($user);
    }

    public function update(User $user, ContactMessage $contactMessage): Response
    {
        return $this->allowPlatformUser($user);
    }

    public function delete(User $user, ContactMessage $contactMessage): Response
    {
        return $this->allowPlatformUser($user);
    }

    private function allowPlatformUser(User $user): Response
    {
        return (new UserPolicy)->accessPlatform($user)
            ? Response::allow()
            : Response::deny('Only superusers can access contact messages.');
    }
}

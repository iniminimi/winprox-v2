<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContactMessagePolicy
{
    public function viewAny(User $user): Response
    {
        return $user->is_superuser
            ? Response::allow()
            : Response::deny('Only superusers can view contact messages.');
    }

    public function view(User $user, ContactMessage $contactMessage): Response
    {
        return $user->is_superuser
            ? Response::allow()
            : Response::deny('Only superusers can view contact messages.');
    }

    public function create(User $user): Response
    {
        return $user->is_superuser
            ? Response::allow()
            : Response::deny('Only superusers can create contact messages.');
    }

    public function update(User $user, ContactMessage $contactMessage): Response
    {
        return $user->is_superuser
            ? Response::allow()
            : Response::deny('Only superusers can update contact messages.');
    }

    public function delete(User $user, ContactMessage $contactMessage): Response
    {
        return $user->is_superuser
            ? Response::allow()
            : Response::deny('Only superusers can delete contact messages.');
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->isTenantAdmin($user)
            && (int) $user->tenant_id === (int) $endpoint->tenant_id;
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->update($user, $endpoint);
    }

    public function manageApiTokens(User $user): bool
    {
        return $this->isTenantAdmin($user);
    }

    private function isTenantAdmin(User $user): bool
    {
        return $user->tenant_id !== null && $user->isAdmin();
    }
}

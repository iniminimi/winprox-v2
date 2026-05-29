<?php

namespace App\Actions\Team;

use App\Models\Tenant;

class UpdateOrganisationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, array $data): Tenant
    {
        $tenant->update(['name' => $data['name']]);

        return $tenant;
    }
}

<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class UpdateOrganisationAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, array $data, ?int $actorUserId = null): Tenant
    {
        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('email', $data)) {
            $updates['email'] = filled($data['email']) ? $data['email'] : null;
        }
        if (array_key_exists('phone', $data)) {
            $updates['phone'] = filled($data['phone']) ? $data['phone'] : null;
        }
        if (array_key_exists('street', $data)) {
            $updates['street'] = filled($data['street']) ? $data['street'] : null;
        }
        if (array_key_exists('house_number', $data)) {
            $updates['house_number'] = filled($data['house_number']) ? $data['house_number'] : null;
        }
        if (array_key_exists('postal_code', $data)) {
            $updates['postal_code'] = filled($data['postal_code']) ? $data['postal_code'] : null;
        }
        if (array_key_exists('city', $data)) {
            $updates['city'] = filled($data['city']) ? $data['city'] : null;
        }
        if (array_key_exists('country_code', $data)) {
            $updates['country_code'] = filled($data['country_code'])
                ? strtoupper((string) $data['country_code'])
                : null;
        }
        if (array_key_exists('custom_theme_active', $data)) {
            $updates['custom_theme_active'] = (bool) $data['custom_theme_active'];
        }
        if (array_key_exists('custom_theme_bg', $data)) {
            $updates['custom_theme_bg'] = filled($data['custom_theme_bg'])
                ? strtolower((string) $data['custom_theme_bg'])
                : null;
        }
        if (array_key_exists('custom_theme_btn', $data)) {
            $updates['custom_theme_btn'] = filled($data['custom_theme_btn'])
                ? strtolower((string) $data['custom_theme_btn'])
                : null;
        }
        if (array_key_exists('logo_path', $data)) {
            $updates['logo_path'] = $data['logo_path'];
        }
        if (array_key_exists('portal_background_path', $data)) {
            $updates['portal_background_path'] = $data['portal_background_path'];
        }

        if ($updates !== []) {
            $tenant->update($updates);
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.organisation_updated',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: [
                'name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'city' => $tenant->city,
                'country_code' => $tenant->country_code,
                'logo_path' => $tenant->logo_path,
                'portal_background_path' => $tenant->portal_background_path,
                'custom_theme_active' => $tenant->custom_theme_active,
                'custom_theme_bg' => $tenant->custom_theme_bg,
                'custom_theme_btn' => $tenant->custom_theme_btn,
            ],
        );

        return $tenant->fresh();
    }
}

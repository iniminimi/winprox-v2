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
        $updates = [
            'name' => trim((string) $data['name']),
            'email' => filled($data['email'] ?? null) ? $data['email'] : null,
            'phone' => filled($data['phone'] ?? null) ? $data['phone'] : null,
            'street' => filled($data['street'] ?? null) ? $data['street'] : null,
            'house_number' => filled($data['house_number'] ?? null) ? $data['house_number'] : null,
            'postal_code' => filled($data['postal_code'] ?? null) ? $data['postal_code'] : null,
            'city' => filled($data['city'] ?? null) ? $data['city'] : null,
            'country_code' => filled($data['country_code'] ?? null) ? strtoupper((string) $data['country_code']) : null,
            'custom_theme_active' => (bool) ($data['custom_theme_active'] ?? false),
            'custom_theme_bg' => filled($data['custom_theme_bg'] ?? null) ? strtolower($data['custom_theme_bg']) : null,
            'custom_theme_btn' => filled($data['custom_theme_btn'] ?? null) ? strtolower($data['custom_theme_btn']) : null,
        ];

        if (array_key_exists('logo_path', $data)) {
            $updates['logo_path'] = $data['logo_path'];
        }

        if (array_key_exists('portal_background_path', $data)) {
            $updates['portal_background_path'] = $data['portal_background_path'];
        }

        $tenant->update($updates);

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

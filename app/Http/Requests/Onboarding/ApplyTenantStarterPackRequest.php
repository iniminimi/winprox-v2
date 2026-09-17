<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Enums\TenantStarterPackSize;
use App\Enums\TenantStarterPackType;
use App\Models\Tenant;
use App\Support\Platform\SupportTenantContext;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyTenantStarterPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $tenantId = Tenancy::id()
            ?? SupportTenantContext::activeTenantId()
            ?? $user?->tenant_id;
        $tenant = $tenantId ? Tenant::query()->find((int) $tenantId) : null;

        return $user !== null
            && $tenant !== null
            && $user->can('applyStarterPack', $tenant);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?string $starterPackType = null): array
    {
        $type = TenantStarterPackType::tryFrom((string) $starterPackType);
        $sizeRequired = $type?->asksCompanySize() ?? false;

        return [
            'starterPackType' => ['required', 'string', Rule::enum(TenantStarterPackType::class)],
            'starterPackSize' => $sizeRequired
                ? ['required', 'string', Rule::enum(TenantStarterPackSize::class)]
                : ['nullable', 'string', Rule::enum(TenantStarterPackSize::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'starterPackType.required' => __('dashboard.starter_pack.errors.type_required'),
            'starterPackType.enum' => __('dashboard.starter_pack.errors.unknown'),
            'starterPackSize.required' => __('dashboard.starter_pack.errors.size_required'),
            'starterPackSize.enum' => __('dashboard.starter_pack.errors.size_required'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet($this->input('starterPackType'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messageSet();
    }
}

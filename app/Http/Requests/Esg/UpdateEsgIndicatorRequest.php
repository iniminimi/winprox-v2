<?php

namespace App\Http\Requests\Esg;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEsgIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = auth()->user()?->tenant_id;
        $indicatorId = $this->route('esgIndicator')?->id ?? $this->input('indicator_id');

        return StoreEsgIndicatorRequest::ruleSet(
            $tenantId ? (int) $tenantId : null,
            $indicatorId ? (int) $indicatorId : null,
        );
    }
}

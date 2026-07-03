<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyPromoCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'copySlug.regex' => __('platform.promo_campaigns.slug_invalid'),
            'copySlug.unique' => __('platform.promo_campaigns.slug_taken'),
            'copyFromCampaignId.exists' => __('platform.promo_campaigns.copy_source_missing'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ruleSet(): array
    {
        return [
            'copyFromCampaignId' => ['required', 'integer', Rule::exists('promo_campaigns', 'id')],
            'copySlug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('promo_campaigns', 'slug')],
            'copyName' => ['required', 'string', 'max:255'],
            'copyLocale' => ['required', 'string', 'max:5'],
        ];
    }
}

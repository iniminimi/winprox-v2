<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use App\Enums\PromoLanding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePromoCampaignRequest extends FormRequest
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
            'slug.regex' => __('platform.promo_campaigns.slug_invalid'),
            'slug.unique' => __('platform.promo_campaigns.slug_taken'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ruleSet(): array
    {
        return [
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('promo_campaigns', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:5'],
            'landing' => ['required', Rule::enum(PromoLanding::class)],
        ];
    }
}

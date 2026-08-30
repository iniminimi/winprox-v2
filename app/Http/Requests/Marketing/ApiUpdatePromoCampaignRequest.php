<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use App\Enums\PromoLanding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiUpdatePromoCampaignRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public static function ruleSet(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:5'],
            'landing' => ['required', Rule::enum(PromoLanding::class)],
            'letter_body_html' => ['nullable', 'string'],
            'email_subject' => ['nullable', 'string', 'max:255'],
            'email_body_html' => ['nullable', 'string'],
            'flow_image_path' => ['nullable', 'string', 'max:500'],
            'youtube_url' => ['nullable', 'string', 'max:500'],
            'column_mapping' => ['nullable', 'array'],
            'column_mapping.name' => ['nullable', 'string', 'max:255'],
            'column_mapping.email' => ['nullable', 'string', 'max:255'],
            'column_mapping.street_address' => ['nullable', 'string', 'max:255'],
            'column_mapping.postal_code' => ['nullable', 'string', 'max:255'],
            'column_mapping.city' => ['nullable', 'string', 'max:255'],
        ];
    }
}

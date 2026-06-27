<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromoCampaignRequest extends FormRequest
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
            'letterBodyHtml' => ['nullable', 'string'],
            'emailSubject' => ['nullable', 'string', 'max:255'],
            'emailBodyHtml' => ['nullable', 'string'],
            'flowImagePath' => ['nullable', 'string', 'max:500'],
            'mapName' => ['nullable', 'string', 'max:255'],
            'mapEmail' => ['nullable', 'string', 'max:255'],
            'mapStreetAddress' => ['nullable', 'string', 'max:255'],
            'mapPostalCode' => ['nullable', 'string', 'max:255'],
            'mapCity' => ['nullable', 'string', 'max:255'],
        ];
    }
}

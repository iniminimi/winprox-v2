<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use App\Enums\PromoVisitPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackPromoEngageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'page' => ['required', 'string', Rule::enum(PromoVisitPage::class)],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Enums\QrPrintablePageBackgroundPreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetOrganisationPortalStockBackgroundRequest extends FormRequest
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
            'portalBackgroundStockPreset' => [
                'required',
                'string',
                Rule::in(QrPrintablePageBackgroundPreset::uiChoiceValues()),
            ],
        ];
    }
}

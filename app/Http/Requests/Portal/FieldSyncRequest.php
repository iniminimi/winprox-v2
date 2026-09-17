<?php

namespace App\Http\Requests\Portal;

use App\Enums\FieldSyncType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FieldSyncRequest extends FormRequest
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
            'client_id' => ['required', 'uuid'],
            'type' => ['required', 'string', Rule::in(FieldSyncType::values())],
            'unit_token' => ['required', 'string', 'max:64'],
            'payload' => ['nullable', 'string', 'max:20000'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['file', 'image', 'max:10240'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decodedPayload(): array
    {
        $raw = $this->input('payload');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}

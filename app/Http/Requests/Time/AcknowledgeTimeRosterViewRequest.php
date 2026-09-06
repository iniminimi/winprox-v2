<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeTimeRosterViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'acknowledged' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'acknowledged.accepted' => __('time.roster.ack_required'),
        ];
    }
}

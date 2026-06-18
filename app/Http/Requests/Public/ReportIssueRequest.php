<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class ReportIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public static function portalRules(): array
    {
        return [
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'reporter_first_name' => ['nullable', 'string', 'max:120'],
            'reporter_last_name' => ['nullable', 'string', 'max:120'],
            'reporter_email' => ['nullable', 'email', 'max:255'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'max:10240'],
        ];
    }

    public function rules(): array
    {
        return self::portalRules();
    }

    public function messages(): array
    {
        return self::validationMessages();
    }

    /** @return array<string, string> */
    public static function validationMessages(): array
    {
        return [
            'description.required' => __('portal.report.errors.description_required'),
            'description.min' => __('portal.report.errors.description_required'),
            'description.max' => __('portal.report.errors.description_max'),
            'reporter_email.email' => __('portal.report.errors.reporter_email_invalid'),
            'photos.max' => __('portal.report.errors.photos_max'),
            'photos.*.image' => __('portal.report.errors.photos_image'),
            'photos.*.max' => __('portal.report.errors.photos_size'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{description: string, reporter_name: ?string, reporter_contact: ?string, original_language?: ?string}
     */
    public static function issueDataFromInput(array $input): array
    {
        $first = trim((string) ($input['reporter_first_name'] ?? ''));
        $last = trim((string) ($input['reporter_last_name'] ?? ''));
        $name = trim($first.' '.$last);
        $email = trim((string) ($input['reporter_email'] ?? ''));

        $data = [
            'description' => trim((string) ($input['description'] ?? '')),
            'reporter_name' => $name !== '' ? $name : null,
            'reporter_contact' => $email !== '' ? $email : null,
        ];

        if (array_key_exists('original_language', $input)) {
            $data['original_language'] = $input['original_language'];
        }

        return $data;
    }
}

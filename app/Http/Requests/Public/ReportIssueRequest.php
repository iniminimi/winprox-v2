<?php

namespace App\Http\Requests\Public;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class ReportIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public static function portalRules(bool $requireReporterContact = false): array
    {
        $nameRules = $requireReporterContact
            ? ['required', 'string', 'max:120']
            : ['nullable', 'string', 'max:120'];

        $emailRules = $requireReporterContact
            ? ['required', 'email', 'max:255']
            : ['nullable', 'email', 'max:255'];

        return [
            'description' => ['required', 'string', 'min:3', 'max:'.TextDescriptionLimits::MAX],
            'reporter_first_name' => $nameRules,
            'reporter_last_name' => $nameRules,
            'reporter_email' => $emailRules,
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
            'reporter_first_name.required' => __('portal.report.errors.reporter_first_name_required'),
            'reporter_last_name.required' => __('portal.report.errors.reporter_last_name_required'),
            'reporter_email.required' => __('portal.report.errors.reporter_email_required'),
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

<?php

namespace App\Actions\Marketing;

use App\Mail\DemoRequestConfirmationMail;
use App\Mail\DemoRequestOpsMail;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SubmitDemoRequestAction
{
    private const COOLDOWN_SECONDS = 60;

    private const MAX_PER_IP_PER_HOUR = 5;

    /**
     * @param  array{company: string, name: string, email: string, phone: string, municipality: string, preference: string, note?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, string $clientIp, string $locale): ContactMessage
    {
        $clientIp = trim($clientIp);

        if ($clientIp !== '') {
            $cooldownKey = 'demo-request:cooldown:'.$clientIp;
            $hourKey = 'demo-request:hour:'.$clientIp;

            if (RateLimiter::tooManyAttempts($cooldownKey, 1)
                || RateLimiter::tooManyAttempts($hourKey, self::MAX_PER_IP_PER_HOUR)) {
                throw ValidationException::withMessages([
                    'form' => __('demo.errors.rate_limited'),
                ]);
            }
        }

        $preferenceLabel = (string) __('demo.form.preference_'.$data['preference'], [], $locale);

        $message = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => __('demo.stored_subject', ['company' => $data['company']], 'nl'),
            'message' => $this->composeMessage($data, $preferenceLabel),
            'direction' => 'inbound',
        ]);

        Mail::to((string) config('winprox.new_tenant_notification_email', 'info@winprox.app'))
            ->send(new DemoRequestOpsMail($data, $preferenceLabel));

        Mail::to($data['email'])
            ->send(new DemoRequestConfirmationMail($data['name'], $data['preference'], $locale));

        if ($clientIp !== '') {
            RateLimiter::hit($cooldownKey, self::COOLDOWN_SECONDS);
            RateLimiter::hit($hourKey, 3600);
        }

        return $message;
    }

    /**
     * @param  array{company: string, name: string, email: string, phone: string, municipality: string, preference: string, note?: ?string}  $data
     */
    private function composeMessage(array $data, string $preferenceLabel): string
    {
        $lines = [
            __('demo.fields.company', [], 'nl').': '.$data['company'],
            __('demo.fields.name', [], 'nl').': '.$data['name'],
            __('demo.fields.email', [], 'nl').': '.$data['email'],
            __('demo.fields.phone', [], 'nl').': '.$data['phone'],
            __('demo.fields.municipality', [], 'nl').': '.$data['municipality'],
            __('demo.fields.preference', [], 'nl').': '.$preferenceLabel,
        ];

        if (filled($data['note'] ?? null)) {
            $lines[] = __('demo.fields.note', [], 'nl').': '.$data['note'];
        }

        return implode("\n", $lines);
    }
}

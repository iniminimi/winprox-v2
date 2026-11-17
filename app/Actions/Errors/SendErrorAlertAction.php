<?php

namespace App\Actions\Errors;

use App\Mail\ErrorAlertMail;
use App\Support\Errors\ErrorReference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mailt WinProx (info@) bij een gelogde serverfout.
 * Max. 1 mail per fouttype (class + eerste plaats in app/, anders bestand:regel) per uur en 10 mails per uur in totaal.
 */
final class SendErrorAlertAction
{
    public const PER_TYPE_SECONDS = 3600;

    public const MAX_PER_HOUR = 10;

    /**
     * @param  array<string, int|string>  $context  zie ErrorReference::logContext()
     */
    public function handle(Throwable $e, array $context): bool
    {
        $to = trim((string) config('winprox.error_alert_email'));

        if ($to === '') {
            return false;
        }

        try {
            $cache = Cache::store((string) config('winprox.error_alert_cache_store', 'file'));
            $fingerprint = sha1($e::class.'|'.(ErrorReference::appFrame($e) ?? $e->getFile().':'.$e->getLine()));
            $suppressedKey = 'error_alert:suppressed:'.$fingerprint;

            if (! $cache->add('error_alert:type:'.$fingerprint, true, self::PER_TYPE_SECONDS)) {
                $cache->increment($suppressedKey);

                return false;
            }

            $hourKey = 'error_alert:hour:'.now()->format('YmdH');
            $cache->add($hourKey, 0, 3600);

            if ($cache->increment($hourKey) > self::MAX_PER_HOUR) {
                $cache->increment($suppressedKey);

                return false;
            }

            $repeated = (int) $cache->pull($suppressedKey, 0);

            Mail::to($to)->send(new ErrorAlertMail($e, $context, $repeated));

            return true;
        } catch (Throwable $alertError) {
            // Niet report(): een mislukte alert mag geen nieuwe alert veroorzaken.
            Log::warning('error_alert_failed', [
                'error_reference' => $context['error_reference'] ?? null,
                'reason' => $alertError->getMessage(),
            ]);

            return false;
        }
    }
}

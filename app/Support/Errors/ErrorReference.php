<?php

namespace App\Support\Errors;

use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Throwable;

/**
 * Korte, willekeurige referentie (WP-XXXXXX) per gelogde serverfout.
 * Dezelfde code staat in het logrecord en op de foutpagina of in het API-antwoord.
 */
class ErrorReference
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 6;

    private const CONTEXT_REFERENCE = 'error_reference';

    private const CONTEXT_EXCEPTION = 'error_reference_exception';

    private const SENSITIVE_PARAMETER = '/token|hash|signature|secret|code|key/i';

    public static function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $chars = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return 'WP-'.$chars;
    }

    /**
     * Logcontext voor een gerapporteerde fout. De referentie blijft voor deze request
     * verborgen in Laravel Context, zodat de foutpagina dezelfde code toont.
     *
     * @return array<string, int|string>
     */
    public static function logContext(Throwable $e): array
    {
        $reference = self::generate();

        Context::addHidden(self::CONTEXT_REFERENCE, $reference);
        Context::addHidden(self::CONTEXT_EXCEPTION, spl_object_id($e));

        return array_filter(
            ['error_reference' => $reference] + self::requestContext(),
            fn ($value) => $value !== null,
        );
    }

    public static function for(?Throwable $e): ?string
    {
        $exceptionId = Context::getHidden(self::CONTEXT_EXCEPTION);

        if ($e === null || $exceptionId === null) {
            return null;
        }

        $matches = $exceptionId === spl_object_id($e)
            || ($e->getPrevious() !== null && $exceptionId === spl_object_id($e->getPrevious()));

        return $matches ? Context::getHidden(self::CONTEXT_REFERENCE) : null;
    }

    /**
     * Alleen bij een echte HTTP-request (met route). In queue/console kan de tenant-override
     * van een vorige taak nog actief zijn; daar loggen we geen tenant of URL.
     *
     * @return array<string, int|string|null>
     */
    private static function requestContext(): array
    {
        try {
            $request = request();
            $route = $request->route();
        } catch (Throwable) {
            return [];
        }

        if (! $route instanceof Route) {
            return [];
        }

        return [
            'tenant_id' => self::tenantId(),
            'method' => $request->method(),
            'url' => self::safeUrl($request, $route),
            'livewire_component' => self::livewireComponent($request),
        ];
    }

    private static function tenantId(): ?int
    {
        try {
            return Tenancy::id();
        } catch (Throwable) {
            return null;
        }
    }

    /** URL zonder querystring; tokens of hashes in het pad worden vervangen door de parameternaam. */
    private static function safeUrl(Request $request, Route $route): string
    {
        $url = $request->url();

        foreach ($route->originalParameters() as $name => $value) {
            if (is_string($value) && $value !== '' && preg_match(self::SENSITIVE_PARAMETER, (string) $name)) {
                $url = str_replace('/'.rawurlencode($value), '/{'.$name.'}', $url);
            }
        }

        return $url;
    }

    private static function livewireComponent(Request $request): ?string
    {
        try {
            if (! Livewire::isLivewireRequest()) {
                return null;
            }

            $snapshot = json_decode((string) $request->input('components.0.snapshot'), true);
            $name = data_get($snapshot, 'memo.name');

            return is_string($name) ? $name : null;
        } catch (Throwable) {
            return null;
        }
    }
}

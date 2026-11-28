<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * USB-flash alleen vanaf deze computer. De pagina op winprox.app mag
 * 127.0.0.1 vragen; een andere site of een ander adres niet.
 */
class EnsureLoopbackUsbFlash
{
    /** @var list<string> */
    private const ORIGINS = [
        'https://winprox.app',
        'http://localhost',
        'http://127.0.0.1',
        'http://winprox.test',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            abort(403);
        }

        $origin = (string) $request->headers->get('Origin', '');
        if (! in_array($origin, self::ORIGINS, true)) {
            abort(403);
        }

        if ($request->isMethod('OPTIONS')) {
            return response('', 204)->withHeaders($this->cors($origin));
        }

        $response = $next($request);
        foreach ($this->cors($origin) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function cors(string $origin): array
    {
        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept',
            'Access-Control-Allow-Private-Network' => 'true',
            'Vary' => 'Origin',
        ];
    }
}

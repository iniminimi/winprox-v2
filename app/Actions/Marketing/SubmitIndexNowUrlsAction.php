<?php

declare(strict_types=1);

namespace App\Actions\Marketing;

use App\Support\Marketing\MarketingSeo;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Submit marketing URLs to IndexNow (Bing and other participants).
 */
final class SubmitIndexNowUrlsAction
{
    /**
     * @param  list<string>  $urls
     * @return array{
     *     submitted: int,
     *     host: string,
     *     key_location: string,
     *     status: int|null,
     *     dry_run: bool,
     *     error: string|null
     * }
     */
    public function handle(array $urls = [], bool $dryRun = false): array
    {
        if (! config('indexnow.enabled', true)) {
            throw new RuntimeException('IndexNow is disabled (INDEXNOW_ENABLED=false).');
        }

        $key = trim((string) config('indexnow.key', ''));
        if ($key === '') {
            throw new RuntimeException('IndexNow key is not configured (INDEXNOW_KEY).');
        }

        $host = $this->resolveHost();
        $keyLocation = 'https://'.$host.'/'.$key.'.txt';
        $urlList = $urls !== [] ? array_values(array_unique($urls)) : MarketingSeo::sitemapUrls();

        if ($urlList === []) {
            throw new RuntimeException('No URLs to submit to IndexNow.');
        }

        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $keyLocation,
            'urlList' => $urlList,
        ];

        if ($dryRun) {
            return [
                'submitted' => count($urlList),
                'host' => $host,
                'key_location' => $keyLocation,
                'status' => null,
                'dry_run' => true,
                'error' => null,
            ];
        }

        $endpoint = (string) config('indexnow.endpoint', 'https://api.indexnow.org/indexnow');

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($endpoint, $payload);

        $status = $response->status();
        $ok = $response->successful() || $status === 202;

        if (! $ok) {
            return [
                'submitted' => 0,
                'host' => $host,
                'key_location' => $keyLocation,
                'status' => $status,
                'dry_run' => false,
                'error' => mb_substr(trim($response->body()), 0, 500) ?: 'IndexNow request failed.',
            ];
        }

        return [
            'submitted' => count($urlList),
            'host' => $host,
            'key_location' => $keyLocation,
            'status' => $status,
            'dry_run' => false,
            'error' => null,
        ];
    }

    private function resolveHost(): string
    {
        $configured = trim((string) config('indexnow.host', ''));
        if ($configured !== '') {
            return strtolower(preg_replace('#^https?://#i', '', $configured) ?? $configured);
        }

        $fromApp = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($fromApp) && $fromApp !== ''
            ? strtolower($fromApp)
            : 'winprox.app';
    }
}

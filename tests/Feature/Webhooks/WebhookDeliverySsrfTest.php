<?php

use App\Actions\Webhooks\DeliverWebhookAction;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy;
use App\Support\Webhook\PublicUrlGuard;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;

afterEach(function () {
    PublicUrlGuard::useDnsResolver(null);
    Tenancy::forget();
});

it('pint het eerste publieke ip en resolvet niet opnieuw', function () {
    $calls = 0;

    withWebhookEnv('production', function () use (&$calls) {
        PublicUrlGuard::useDnsResolver(function () use (&$calls) {
            $calls++;

            return $calls === 1 ? ['1.2.3.4'] : ['10.0.0.1'];
        });

        $spec = PublicUrlGuard::deliveryOptions('https://evil.example/hook');

        expect($calls)->toBe(1)
            ->and($spec['url'])->toBe('https://evil.example/hook')
            ->and($spec['options']['allow_redirects'])->toBeFalse()
            ->and($spec['options']['proxy'])->toBe('')
            ->and($spec['options']['stream'])->toBeTrue()
            ->and($spec['options']['connect_timeout'])->toBe(5)
            ->and($spec['options']['timeout'])->toBe(15)
            ->and($spec['options']['curl'][CURLOPT_RESOLVE])->toBe(['evil.example:443:1.2.3.4']);
    });
});

it('keurt een hostname af zodra één adres privé is', function () {
    withWebhookEnv('production', function () {
        PublicUrlGuard::useDnsResolver(fn () => ['1.2.3.4', '10.0.0.1']);

        expect(fn () => PublicUrlGuard::assertAllowed('https://evil.example/hook'))
            ->toThrow(InvalidArgumentException::class, 'webhook_url_private_host_not_allowed');
    });
});

it('zet een ipv6-pin tussen haken', function () {
    withWebhookEnv('production', function () {
        PublicUrlGuard::useDnsResolver(fn () => ['2606:4700:4700::1111']);

        $spec = PublicUrlGuard::deliveryOptions('https://dns.example/hook');

        expect($spec['options']['curl'][CURLOPT_RESOLVE])
            ->toBe(['dns.example:443:[2606:4700:4700::1111]']);
    });
});

it('blokkeert geen-publieke ip-literals', function (string $url) {
    withWebhookEnv('production', function () use ($url) {
        expect(fn () => PublicUrlGuard::assertAllowed($url))
            ->toThrow(InvalidArgumentException::class, 'webhook_url_private_host_not_allowed');
    });
})->with([
    'loopback' => 'http://127.0.0.1/hook',
    'ipv6 loopback' => 'http://[::1]/hook',
    'mapped loopback' => 'http://[::ffff:127.0.0.1]/hook',
    'metadata' => 'http://169.254.169.254/latest',
    'zero network' => 'http://0.1.2.3/',
    'cgnat' => 'http://100.100.100.200/',
    'private' => 'http://10.1.2.3/',
    'ietf protocol' => 'http://192.0.0.8/',
    'benchmarking' => 'http://198.18.0.1/',
    'multicast' => 'http://224.0.0.1/',
    'reserved' => 'http://240.0.0.1/',
    'nat64' => 'http://[64:ff9b::1]/',
    '6to4' => 'http://[2002::1]/',
    'teredo' => 'http://[2001:0::1]/',
    'ula' => 'http://[fc00::1]/',
    'link local' => 'http://[fe80::1]/',
    'ipv6 multicast' => 'http://[ff02::1]/',
]);

it('wijst vermomde ip-literals af zonder dns', function (string $url) {
    $called = false;
    PublicUrlGuard::useDnsResolver(function () use (&$called) {
        $called = true;

        return ['1.2.3.4'];
    });

    expect(fn () => PublicUrlGuard::assertAllowed($url))
        ->toThrow(InvalidArgumentException::class, 'webhook_url_host_not_allowed');
    expect($called)->toBeFalse();
})->with([
    'decimal' => 'http://2130706433/',
    'hex' => 'http://0x7f.1/',
    'short' => 'http://127.1/',
]);

it('wijst userinfo in de url af', function () {
    expect(fn () => PublicUrlGuard::assertAllowed('https://user@hooks.example/hook'))
        ->toThrow(InvalidArgumentException::class, 'webhook_url_credentials_not_allowed');
});

it('wijst een niet-ascii-host af of pint de punycode-vorm', function () {
    withWebhookEnv('production', function () {
        if (! function_exists('idn_to_ascii')) {
            expect(fn () => PublicUrlGuard::assertAllowed('https://exämple.test/hook'))
                ->toThrow(InvalidArgumentException::class, 'webhook_url_host_not_allowed');

            return;
        }

        PublicUrlGuard::useDnsResolver(fn () => ['1.2.3.4']);
        $spec = PublicUrlGuard::deliveryOptions('https://exämple.test/hook');

        expect($spec['url'])->toStartWith('https://xn--')
            ->and($spec['options']['curl'][CURLOPT_RESOLVE][0])->toStartWith('xn--');
    });
});

it('weigert een niet-standaard poort buiten local en testing', function () {
    $called = false;
    PublicUrlGuard::useDnsResolver(function () use (&$called) {
        $called = true;

        return ['1.2.3.4'];
    });

    withWebhookEnv('staging', function () {
        expect(fn () => PublicUrlGuard::assertAllowed('https://evil.example:8443/hook'))
            ->toThrow(InvalidArgumentException::class, 'webhook_url_port_not_allowed');
    });

    expect($called)->toBeFalse();
});

it('laat private hosts toe in local en testing, met redirects uit', function (string $env) {
    withWebhookEnv($env, function () {
        $spec = PublicUrlGuard::deliveryOptions('http://127.0.0.1:9/hook');

        expect($spec['url'])->toBe('http://127.0.0.1:9/hook')
            ->and($spec['options'])->not->toHaveKey('curl')
            ->and($spec['options']['allow_redirects'])->toBeFalse();
    });
})->with(['local', 'testing']);

it('kapt een response zonder content-length af boven 64 kib', function () {
    $spec = PublicUrlGuard::deliveryOptions('http://127.0.0.1/hook');
    $sink = $spec['options']['sink'];

    expect($sink->write(str_repeat('a', PublicUrlGuard::MAX_RESPONSE_BYTES)))->toBe(PublicUrlGuard::MAX_RESPONSE_BYTES);
    expect(fn () => $sink->write('b'))->toThrow(RuntimeException::class, 'webhook_response_too_large');
});

it('kapt een te grote content-length af voor de body', function () {
    $spec = PublicUrlGuard::deliveryOptions('http://127.0.0.1/hook');
    $onHeaders = $spec['options']['on_headers'];

    expect(fn () => $onHeaders(new Response(200, ['Content-Length' => (string) (PublicUrlGuard::MAX_RESPONSE_BYTES + 1)])))
        ->toThrow(RuntimeException::class, 'webhook_response_too_large');
});

it('post niet wanneer de guard de url afkeurt', function () {
    withWebhookEnv('production', function () {
        PublicUrlGuard::useDnsResolver(fn () => ['10.0.0.8']);
        Http::fake();

        $tenant = Tenant::factory()->create();
        Tenancy::actAs($tenant->id);
        $endpoint = WebhookEndpoint::factory()->create([
            'tenant_id' => $tenant->id,
            'url' => 'https://evil.example/hook',
        ]);
        $delivery = WebhookDelivery::query()->create([
            'tenant_id' => $tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'issue.created',
            'payload' => ['ok' => true],
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        $result = app(DeliverWebhookAction::class)->handle($delivery, 1, 4);

        Http::assertNothingSent();
        expect($result)->toBe(['success' => false, 'should_retry' => false])
            ->and($delivery->fresh()->status)->toBe(WebhookDelivery::STATUS_FAILED)
            ->and($delivery->fresh()->error)->toBe('webhook_url_private_host_not_allowed');
    });
});

it('volgt een 302 van een lokale server niet', function () {
    $server = startWebhookRedirectServer();

    try {
        $stderr = is_file($server['stderr']) ? (string) file_get_contents($server['stderr']) : '';
        expect($server['ready'])->toBeTrue($stderr);

        $tenant = Tenant::factory()->create();
        Tenancy::actAs($tenant->id);
        $endpoint = WebhookEndpoint::factory()->create([
            'tenant_id' => $tenant->id,
            'url' => 'http://127.0.0.1:'.$server['port'].'/hook',
        ]);
        $delivery = WebhookDelivery::query()->create([
            'tenant_id' => $tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'issue.created',
            'payload' => ['ok' => true],
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        $result = app(DeliverWebhookAction::class)->handle($delivery, 1, 1);
        $delivery->refresh();
        $hits = is_file($server['hits'])
            ? file($server['hits'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : [];

        expect($result['success'])->toBeFalse()
            ->and($delivery->response_status)->toBe(302)
            ->and($delivery->error)->toBe('HTTP 302')
            ->and($hits)->toBe(['/hook']);
    } finally {
        ($server['stop'])();
    }
});

function withWebhookEnv(string $env, callable $callback): mixed
{
    $previous = app()['env'];
    app()['env'] = $env;

    try {
        return $callback();
    } finally {
        app()['env'] = $previous;
    }
}

/**
 * @return array{port: int, hits: string, stderr: string, ready: bool, stop: callable(): void}
 */
function startWebhookRedirectServer(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    expect($socket)->not->toBeFalse();
    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);

    $dir = sys_get_temp_dir();
    $id = uniqid('', true);
    $hits = $dir.DIRECTORY_SEPARATOR.'wp-webhook-hits-'.$id;
    $router = $dir.DIRECTORY_SEPARATOR.'wp-webhook-router-'.$id.'.php';
    $stdout = $dir.DIRECTORY_SEPARATOR.'wp-webhook-out-'.$id;
    $stderr = $dir.DIRECTORY_SEPARATOR.'wp-webhook-err-'.$id;
    $devnull = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';

    file_put_contents($router, <<<'PHP'
<?php
$log = getenv('WP_HITS');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
file_put_contents($log, $uri."\n", FILE_APPEND);
if ($uri === '/hook') {
    header('Location: http://127.0.0.1:'.getenv('WP_PORT').'/secret', true, 302);
    header('Content-Length: 0');

    return true;
}
http_response_code(200);
echo 'followed';

return true;
PHP);

    $env = getenv();
    if (! is_array($env)) {
        $env = [];
    }
    $env['WP_HITS'] = $hits;
    $env['WP_PORT'] = (string) $port;

    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:'.$port, $router],
        [
            0 => ['file', $devnull, 'r'],
            1 => ['file', $stdout, 'w'],
            2 => ['file', $stderr, 'w'],
        ],
        $pipes,
        null,
        $env,
        ['bypass_shell' => true],
    );

    $ready = false;
    if (is_resource($process)) {
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (is_resource($fp)) {
                fclose($fp);
                $ready = true;
                break;
            }
            usleep(50000);
        }
    }

    return [
        'port' => $port,
        'hits' => $hits,
        'stderr' => $stderr,
        'ready' => $ready,
        'stop' => function () use ($process, $router, $hits, $stdout, $stderr): void {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            @unlink($router);
            @unlink($hits);
            @unlink($stdout);
            @unlink($stderr);
        },
    ];
}

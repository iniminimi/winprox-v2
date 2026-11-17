<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Errors\ErrorReference;
use App\Support\Tenancy;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;

const ERROR_REFERENCE_PATTERN = '/WP-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}/';
const TECHNICAL_DETAIL = 'SQLSTATE[23000]: Duplicate entry secret@example.com in /var/www/app/Secret.php';

class ErrorHandlingThrowingComponent extends Component
{
    public function explode(): void
    {
        throw new RuntimeException(TECHNICAL_DETAIL);
    }

    public function render(): string
    {
        return '<div>ok</div>';
    }
}

beforeEach(function () {
    Tenancy::forget();
    config(['app.debug' => false]);

    Route::middleware('web')->get('/_test-error-500', fn () => throw new RuntimeException(TECHNICAL_DETAIL));
    Route::middleware('web')->get('/_test-error-token/{token}', fn () => throw new RuntimeException(TECHNICAL_DETAIL));
    Route::middleware('api')->get('/api/_test-error-500', fn () => throw new RuntimeException(TECHNICAL_DETAIL));
});

afterEach(fn () => Tenancy::forget());

function captureErrorLogs(): Collection
{
    $logs = collect();
    Event::listen(MessageLogged::class, fn (MessageLogged $event) => $logs->push($event));

    return $logs;
}

function loggedErrorReference(Collection $logs): ?string
{
    return $logs->first(fn (MessageLogged $e) => isset($e->context['error_reference']))?->context['error_reference'];
}

it('generates short random references in the WP-XXXXXX format', function () {
    $references = collect(range(1, 200))->map(fn () => ErrorReference::generate());

    $references->each(fn (string $ref) => expect($ref)->toMatch('/^WP-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/'));
    expect($references->unique()->count())->toBeGreaterThan(195);
});

it('shows the same reference on the 500 page as in the log, without technical details', function () {
    $logs = captureErrorLogs();

    $response = $this->get('/_test-error-500');

    $response->assertStatus(500);
    preg_match(ERROR_REFERENCE_PATTERN, $response->getContent(), $match);

    expect($match)->not->toBeEmpty()
        ->and(loggedErrorReference($logs))->toBe($match[0]);

    $response->assertSee(__('error.500.message'), false)
        ->assertSee(__('error.reference_hint'), false)
        ->assertDontSee('SQLSTATE', false)
        ->assertDontSee('secret@example.com', false)
        ->assertDontSee('/var/www', false)
        ->assertDontSee('RuntimeException', false);

    $record = $logs->first(fn (MessageLogged $e) => isset($e->context['error_reference']));
    expect($record->level)->toBe('error')
        ->and($record->message)->toContain('SQLSTATE')
        ->and($record->context['exception'])->toBeInstanceOf(RuntimeException::class);
});

it('logs tenant, user, method and a safe URL with the reference', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $logs = captureErrorLogs();

    $this->actingAs($user)->get('/_test-error-token/abc123secret?password=hunter2')->assertStatus(500);

    $context = $logs->first(fn (MessageLogged $e) => isset($e->context['error_reference']))->context;

    expect($context['tenant_id'])->toBe($tenant->id)
        ->and($context['userId'])->toBe($user->id)
        ->and($context['method'])->toBe('GET')
        ->and($context['url'])->toEndWith('/_test-error-token/{token}')
        ->and(json_encode($context['url']))->not->toContain('abc123secret')
        ->and(json_encode($context['url']))->not->toContain('hunter2');
});

it('logs the support tenant when a superuser acts for a tenant', function () {
    $tenant = Tenant::factory()->create();
    $superuser = User::factory()->superuser()->create();
    Route::middleware('web')->get('/_test-error-support', function () use ($tenant) {
        Tenancy::actAs($tenant->id);

        throw new RuntimeException(TECHNICAL_DETAIL);
    });
    $logs = captureErrorLogs();

    $this->actingAs($superuser)->get('/_test-error-support')->assertStatus(500);

    $context = $logs->first(fn (MessageLogged $e) => isset($e->context['error_reference']))->context;
    expect($context['tenant_id'])->toBe($tenant->id)
        ->and($context['userId'])->toBe($superuser->id);
});

it('does not log tenant or URL outside an HTTP request', function () {
    Tenancy::actAs(999);

    $context = ErrorReference::logContext(new RuntimeException('queue failure'));

    expect($context)->toHaveKey('error_reference')
        ->and($context)->not->toHaveKey('tenant_id')
        ->and($context)->not->toHaveKey('url');
});

it('shows no reference on pages for errors that are not logged', function () {
    $logs = captureErrorLogs();

    $response = $this->get('/wp-nonexistent-route-for-reference-test');

    $response->assertNotFound()->assertSee(__('error.404.message'), false);
    expect(preg_match(ERROR_REFERENCE_PATTERN, $response->getContent()))->toBe(0)
        ->and(loggedErrorReference($logs))->toBeNull();
});

it('translates the 500 page and the reference', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $response = $this->actingAs($user)->get('/_test-error-500');

    $response->assertStatus(500)
        ->assertSee('We could not complete this action.', false)
        ->assertSee('Reference: WP-', false);
});

it('returns the reference in API 500 responses without technical details', function () {
    $logs = captureErrorLogs();

    $response = $this->getJson('/api/_test-error-500');

    $response->assertStatus(500)
        ->assertJsonPath('message', 'Server error.')
        ->assertJsonPath('reference', loggedErrorReference($logs));

    expect($response->json('reference'))->toMatch(ERROR_REFERENCE_PATTERN)
        ->and($response->getContent())->not->toContain('SQLSTATE')
        ->and($response->getContent())->not->toContain('secret@example.com');
});

it('shows the 500 page with the logged reference for a failing Livewire request', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    Livewire::component('error-handling-throwing', ErrorHandlingThrowingComponent::class);
    $snapshot = Livewire::actingAs($user)->test(ErrorHandlingThrowingComponent::class)->snapshot;
    $logs = captureErrorLogs();

    $payload = json_encode(['components' => [[
        'snapshot' => json_encode($snapshot),
        'updates' => [],
        'calls' => [['method' => 'explode', 'params' => []]],
    ]]]);

    $response = $this->actingAs($user)->call('POST', app('livewire')->getUpdateUri(), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LIVEWIRE' => '1',
    ], $payload);

    $response->assertStatus(500);
    preg_match(ERROR_REFERENCE_PATTERN, $response->getContent(), $match);
    $context = $logs->first(fn (MessageLogged $e) => isset($e->context['error_reference']))->context;

    expect($match)->not->toBeEmpty()
        ->and($context['error_reference'])->toBe($match[0])
        ->and($context['tenant_id'])->toBe($tenant->id)
        ->and($context['method'])->toBe('POST')
        ->and($response->getContent())->not->toContain('SQLSTATE')
        ->and($response->getContent())->not->toContain('secret@example.com');
});

it('hides function arguments in stack traces in production', function () {
    $original = ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '0');
    app()->detectEnvironment(fn () => 'production');

    try {
        (new App\Providers\AppServiceProvider(app()))->register();

        expect(ini_get('zend.exception_ignore_args'))->toBe('1');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
        ini_set('zend.exception_ignore_args', $original);
    }
});

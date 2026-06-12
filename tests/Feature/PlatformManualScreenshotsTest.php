<?php

use App\Jobs\CaptureManualScreenshotsJob;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

it('wijst niet-superusers af voor platform screenshots', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('platform.screenshots'))
        ->assertForbidden();
});

it('toont de screenshots-pagina voor een superuser', function () {
    $superuser = User::factory()->superuser()->create();

    $this->actingAs($superuser)
        ->get(route('platform.screenshots'))
        ->assertOk()
        ->assertSee(__('platform.manual_screenshots.title'));
});

it('start een screenshot-run via de queue wanneer geconfigureerd', function () {
    Bus::fake();
    Storage::disk('local')->delete((string) config('manual_capture.status_path'));

    Config::set('manual_capture.email', 'demo_user@winprox.app');
    Config::set('manual_capture.password', 'secret');

    $superuser = User::factory()->superuser()->create();

    $this->actingAs($superuser)
        ->get(route('platform.screenshots'))
        ->assertOk();

    \Livewire\Livewire::actingAs($superuser)
        ->test(\App\Livewire\Platform\ManualScreenshots::class)
        ->call('startCapture')
        ->assertSet('flashType', 'success');

    Bus::assertDispatched(CaptureManualScreenshotsJob::class, function (CaptureManualScreenshotsJob $job) use ($superuser) {
        return $job->actorUserId === $superuser->id;
    });
});

it('toont een fout wanneer capture-credentials ontbreken', function () {
    Config::set('manual_capture.email', '');
    Config::set('manual_capture.password', '');

    $superuser = User::factory()->superuser()->create();

    \Livewire\Livewire::actingAs($superuser)
        ->test(\App\Livewire\Platform\ManualScreenshots::class)
        ->call('startCapture')
        ->assertSet('flashType', 'error');
});

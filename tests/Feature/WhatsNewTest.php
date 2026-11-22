<?php

use App\Actions\ReleaseNotes\ListPublishedReleaseNotesAction;
use App\Actions\ReleaseNotes\ShouldShowWhatsNewCardAction;
use App\Livewire\Dashboard;
use App\Livewire\Pages\WhatsNew;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
    Tenancy::forget();
});

it('toont de versiegeschiedenis nieuwste dag eerst en haalt de kaart weg', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'locale' => 'nl']);
    Tenancy::actAs($tenant->id);
    seedTenantPastOnboarding($tenant);
    app()->setLocale('nl');

    $latest = app(ListPublishedReleaseNotesAction::class)->latest();
    expect($latest)->not->toBeNull();
    Carbon::setTestNow($latest->date->copy()->addDays(2));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('whats_new.card_title'), false)
        ->assertSee(__('common.nav.whats_new'), false);

    Livewire::actingAs($user)
        ->test(WhatsNew::class)
        ->assertSee(__('whats_new.title'), false)
        ->assertSee('video/assistant_new.mp4', false)
        ->assertSee('Bij een uitvoerder zie je hoeveel documenten en mededelingen er zijn.', false)
        ->assertDontSee('Je kunt het beheer op de computer als eigen venster zetten', false);

    $user->refresh();
    expect($user->whats_new_dismissed_on?->toDateString())->toBe($latest->date->toDateString());

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee(__('whats_new.card_body'));
});

it('verbergt de kaart na een week en slaat een bestand zonder datum over', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $dir = storage_path('framework/testing/whats-new-'.uniqid());
    File::ensureDirectoryExists($dir);
    File::put($dir.'/2026-11-01.md', "## Nieuw\n- Oude tekst die niet meer op het beginscherm hoort.\n");
    File::put($dir.'/klad.md', "## Nieuw\n- Dit concept hoort nergens.\n");

    $notes = app(ListPublishedReleaseNotesAction::class);
    $show = app(ShouldShowWhatsNewCardAction::class);

    expect($notes->handle($dir))->toHaveCount(1);

    Carbon::setTestNow('2026-11-07');
    expect($notes->recent($dir))->toHaveCount(1);
    expect($show->handle($user, null, $dir))->toBeTrue();

    Carbon::setTestNow('2026-11-08');
    expect($notes->recent($dir))->toHaveCount(0);
    expect($show->handle($user, null, $dir))->toBeFalse();

    File::deleteDirectory($dir);
});

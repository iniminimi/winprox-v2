<?php

use App\Livewire\Platform\Users;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('laat een superuser een wachtwoord zetten zonder mail', function () {
    $tenant = Tenant::factory()->create();
    $superuser = User::factory()->superuser()->create();
    $member = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Iris Lokaal',
        'email' => 'iris@example.com',
    ]);
    DB::table('sessions')->insert([
        'id' => 'member-sess',
        'user_id' => $member->id,
        'payload' => 'x',
        'last_activity' => time(),
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => $member->email,
        'token' => 'reset-token',
        'created_at' => now(),
    ]);

    Livewire::actingAs($superuser)->test(Users::class)
        ->call('openPassword', $member->id)
        ->set('password', 'nieuw-wachtwoord')
        ->set('password_confirmation', 'nieuw-wachtwoord')
        ->call('savePassword')
        ->assertHasNoErrors()
        ->assertSee(__('platform.users.password.saved', ['name' => 'Iris Lokaal']));

    expect(Hash::check('nieuw-wachtwoord', $member->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $member->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $member->email)->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'user.password_set_by_platform')->where('model_id', $member->id)->exists())->toBeTrue();
});

it('weigert een te kort wachtwoord en een gewone beheerder', function () {
    $tenant = Tenant::factory()->create();
    $superuser = User::factory()->superuser()->create();
    $member = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($superuser)->test(Users::class)
        ->call('openPassword', $member->id)
        ->set('password', 'kort')
        ->set('password_confirmation', 'kort')
        ->call('savePassword')
        ->assertHasErrors(['password']);

    Livewire::actingAs($admin)->test(Users::class)
        ->assertForbidden();
});

it('laat een superuser het wachtwoord van een superuser zetten', function () {
    $actor = User::factory()->superuser()->create();
    $target = User::factory()->superuser()->create([
        'name' => 'Plattform Beheer',
        'email' => 'ander@winprox.app',
    ]);
    DB::table('sessions')->insert([
        'id' => 'super-sess',
        'user_id' => $target->id,
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    Livewire::actingAs($actor)->test(Users::class)
        ->assertSee(__('platform.users.password.button'))
        ->call('openPassword', $target->id)
        ->set('password', 'ander-wachtwoord')
        ->set('password_confirmation', 'ander-wachtwoord')
        ->call('savePassword')
        ->assertHasNoErrors()
        ->assertSee(__('platform.users.password.saved', ['name' => 'Plattform Beheer']));

    expect(Hash::check('ander-wachtwoord', $target->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
});

it('houdt de eigen sessie bij het wijzigen van het eigen superuser-wachtwoord', function () {
    $actor = User::factory()->superuser()->create();
    foreach (['keep-me', 'other-device'] as $id) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $actor->id,
            'payload' => 'x',
            'last_activity' => time(),
        ]);
    }

    app(\App\Actions\Platform\SetPlatformUserPasswordAction::class)->handle(
        $actor,
        'eigen-wachtwoord',
        (int) $actor->id,
        'keep-me',
    );

    expect(Hash::check('eigen-wachtwoord', $actor->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'keep-me')->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
});

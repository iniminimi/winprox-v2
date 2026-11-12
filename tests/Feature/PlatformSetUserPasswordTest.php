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

it('weigert een te kort wachtwoord en een superuser als doel', function () {
    $tenant = Tenant::factory()->create();
    $superuser = User::factory()->superuser()->create();
    $otherSuperuser = User::factory()->superuser()->create(['email' => 'ander@winprox.app']);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($superuser)->test(Users::class)
        ->call('openPassword', $member->id)
        ->set('password', 'kort')
        ->set('password_confirmation', 'kort')
        ->call('savePassword')
        ->assertHasErrors(['password']);

    Livewire::actingAs($superuser)->test(Users::class)
        ->call('openPassword', $otherSuperuser->id)
        ->assertForbidden();

    Livewire::actingAs($admin)->test(Users::class)
        ->assertForbidden();
});

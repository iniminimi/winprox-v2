<?php

use App\Livewire\Auth\Login;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

const IPAD_UA = 'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

afterEach(fn () => Tenancy::forget());

it('toont op een smartphone geen inlogformulier', function () {
    $this->withHeader('User-Agent', IPHONE_UA)
        ->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.phone.title'))
        ->assertDontSee(__('auth.password'), false);

    $this->withHeader('User-Agent', IPHONE_UA)
        ->get(route('auth.microsoft.redirect'))
        ->assertOk()
        ->assertSee(__('auth.phone.title'));
});

it('laat een tablet wel inloggen', function () {
    $this->withHeader('User-Agent', IPAD_UA)
        ->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.password'), false)
        ->assertDontSee(__('auth.phone.title'), false);
});

it('blokkeert het beheer op een smartphone voor beheerder en medewerker', function (string $role) {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => $role,
    ]);

    $this->withHeader('User-Agent', IPHONE_UA)
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('auth.phone.title'))
        ->assertDontSee(__('dashboard.welcome'), false);
})->with([
    'beheerder' => User::ROLE_ADMIN,
    'medewerker' => User::ROLE_EMPLOYEE,
]);

it('meldt een smartphone niet aan via het loginformulier', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'gsm@winprox.test',
    ]);

    $component = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password');

    // Livewire::call() vervangt de User-Agent; de echte browser stuurt die wel mee.
    request()->headers->set('User-Agent', IPHONE_UA);
    $component->instance()->login();

    expect(auth()->check())->toBeFalse();
});

it('toont op een smartphone geen registratieformulier', function () {
    $this->withHeader('User-Agent', IPHONE_UA)
        ->get(route('register'))
        ->assertOk()
        ->assertSee(__('auth.phone.title'))
        ->assertDontSee(__('auth.register.title'), false);
});

it('laat de website en wachtwoordherstel op een smartphone open', function () {
    $this->withHeader('User-Agent', IPHONE_UA)
        ->get(route('welcome', ['locale' => 'nl']))
        ->assertOk()
        ->assertDontSee(__('auth.phone.title'), false);

    $this->withHeader('User-Agent', IPHONE_UA)
        ->get(route('password.request'))
        ->assertOk()
        ->assertSee(__('auth.forgot.title'), false);
});

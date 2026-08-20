<?php

use App\Livewire\Auth\Login;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

beforeEach(function () {
    config([
        'services.azure.client_id' => null,
        'services.azure.client_secret' => null,
    ]);
});

function enableEntraForTests(): void
{
    config([
        'services.azure.client_id' => 'test-client-id',
        'services.azure.client_secret' => 'test-client-secret',
        'services.azure.tenant' => 'organizations',
        'services.azure.redirect' => 'http://localhost/auth/microsoft/callback',
    ]);
}

function fakeAzureUser(?string $email, ?string $mail = null, ?string $upn = null): void
{
    $social = (new SocialiteUser)->map([
        'id' => 'entra-oid-1',
        'name' => 'Jan Microsoft',
        'email' => $email,
    ])->setRaw([
        'mail' => $mail,
        'userPrincipalName' => $upn ?? $email,
    ]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://login.microsoftonline.com/fake'));
    $provider->shouldReceive('user')->andReturn($social);

    Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
}

it('verbergt de Microsoft-knop zonder Entra-config', function () {
    Livewire::test(Login::class)
        ->assertDontSee(__('auth.microsoft.submit'), false);
});

it('toont de Microsoft-knop als Entra geconfigureerd is', function () {
    enableEntraForTests();

    Livewire::test(Login::class)
        ->assertSee(__('auth.microsoft.submit'), false);
});

it('registreert de Azure Socialite-driver', function () {
    enableEntraForTests();

    expect(Socialite::driver('azure'))->toBeInstanceOf(SocialiteProviders\Azure\Provider::class);
});

it('geeft 404 op Microsoft-routes zonder Entra-config', function () {
    $this->get(route('auth.microsoft.redirect'))->assertNotFound();
    $this->get(route('auth.microsoft.callback'))->assertNotFound();
});

it('stuurt naar Microsoft bij Inloggen met Microsoft', function () {
    enableEntraForTests();
    fakeAzureUser('jan@winprox.test');

    $this->get(route('auth.microsoft.redirect'))
        ->assertRedirect('https://login.microsoftonline.com/fake');
});

it('logt een bestaande medewerker in via Microsoft-mail', function () {
    enableEntraForTests();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'email' => 'jan@winprox.test',
    ]);
    seedTenantPastOnboarding($tenant);

    fakeAzureUser('jan@tenant.onmicrosoft.com', 'jan@winprox.test', 'jan@tenant.onmicrosoft.com');

    $this->get(route('auth.microsoft.callback'))
        ->assertRedirect(route('dashboard'));

    expect(Auth::id())->toBe($user->id);
});

it('weigert onbekende Microsoft-accounts zonder een user aan te maken', function () {
    enableEntraForTests();
    fakeAzureUser('onbekend@microsoft.test');

    $this->get(route('auth.microsoft.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(Auth::check())->toBeFalse()
        ->and(User::query()->where('email', 'onbekend@microsoft.test')->exists())->toBeFalse();
});

it('weigert inactieve gebruikers via Microsoft', function () {
    enableEntraForTests();

    $tenant = Tenant::factory()->create();
    User::factory()->inactive()->create([
        'tenant_id' => $tenant->id,
        'email' => 'stil@winprox.test',
    ]);

    fakeAzureUser('stil@winprox.test');

    $this->get(route('auth.microsoft.callback'))
        ->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});

it('weigert superusers via Microsoft', function () {
    enableEntraForTests();

    User::factory()->superuser()->create([
        'email' => 'platform@winprox.test',
    ]);

    fakeAzureUser('platform@winprox.test');

    $this->get(route('auth.microsoft.callback'))
        ->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});

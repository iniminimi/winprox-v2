<?php

use App\Actions\Issues\CreateIssueAction;
use App\Actions\Issues\NotifySubscribedUsersOfNewQrIssueAction;
use App\Enums\IssueSource;
use App\Livewire\Pages\Settings;
use App\Livewire\Pages\Team;
use App\Mail\NewQrIssueMail;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('stuurt een e-mail naar alle collega-gebruikers met voorkeur aan bij QR-melding', function () {
    Mail::fake();

    $tenant = Tenant::factory()->create(['name' => 'Acme NV']);
    Tenancy::actAs($tenant->id);

    $admin = User::factory()->admin()->create([
        'tenant_id' => $tenant->id,
        'notify_on_new_issue_email' => true,
    ]);
    $employee = User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'email' => 'employee@acme.test',
        'notify_on_new_issue_email' => true,
    ]);
    User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'email' => 'optout@acme.test',
        'notify_on_new_issue_email' => false,
    ]);

    $issue = app(CreateIssueAction::class)->handle([
        'description' => 'Lekkage bij machine',
        'source' => IssueSource::Qr->value,
        'reporter_name' => 'Jan Melder',
    ]);

    Mail::assertQueued(NewQrIssueMail::class, 2);
    Mail::assertQueued(NewQrIssueMail::class, fn (NewQrIssueMail $mail) => $mail->hasTo($admin->email));
    Mail::assertQueued(NewQrIssueMail::class, fn (NewQrIssueMail $mail) => $mail->hasTo($employee->email));
    Mail::assertNotQueued(NewQrIssueMail::class, fn (NewQrIssueMail $mail) => $mail->hasTo('optout@acme.test'));
});

it('stuurt geen e-mail bij een beheer-melding', function () {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    User::factory()->admin()->create([
        'tenant_id' => $tenant->id,
        'notify_on_new_issue_email' => true,
    ]);

    app(CreateIssueAction::class)->handle([
        'description' => 'Interne melding',
        'source' => IssueSource::Manager->value,
    ]);

    Mail::assertNothingQueued();
});

it('stuurt geen e-mail naar inactieve collega-gebruikers', function () {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    User::factory()->admin()->inactive()->create([
        'tenant_id' => $tenant->id,
        'notify_on_new_issue_email' => true,
    ]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'source' => IssueSource::Qr,
        'description' => 'QR melding',
    ]);

    app(NotifySubscribedUsersOfNewQrIssueAction::class)->handle($issue);

    Mail::assertNothingQueued();
});

it('laat een admin de e-mailvoorkeur van een collega beheren', function () {
    [$tenant, $admin] = [Tenant::factory()->create(), null];
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $colleague = User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'notify_on_new_issue_email' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openEditColleague', $colleague->id)
        ->set('colleagueNotifyOnNewIssueEmail', false)
        ->set('colleaguePassword', '')
        ->set('colleaguePasswordConfirmation', '')
        ->call('saveColleague')
        ->assertHasNoErrors();

    expect($colleague->fresh()->notify_on_new_issue_email)->toBeFalse();
});

it('laat een medewerker de eigen e-mailvoorkeur wijzigen in instellingen', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);
    $employee = User::factory()->employee()->create([
        'tenant_id' => $tenant->id,
        'notify_on_new_issue_email' => true,
    ]);

    Livewire::actingAs($employee)
        ->test(Settings::class)
        ->set('notifyOnNewIssueEmail', false)
        ->assertSet('notifyOnNewIssueEmail', false);

    expect($employee->fresh()->notify_on_new_issue_email)->toBeFalse();
});

it('zet notify_on_new_issue_email standaard aan bij nieuwe collega', function () {
    Notification::fake();
    [$tenant, $admin] = [Tenant::factory()->create(), null];
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openCreateColleague')
        ->set('colleagueName', 'Nieuwe Collega')
        ->set('colleagueEmail', 'nieuw@acme.test')
        ->set('colleagueLocale', 'nl')
        ->set('colleagueRole', User::ROLE_EMPLOYEE)
        ->set('colleaguePassword', 'wachtwoord123')
        ->set('colleaguePasswordConfirmation', 'wachtwoord123')
        ->set('colleagueSendAccountEmail', false)
        ->call('saveColleague')
        ->assertHasNoErrors();

    expect(User::where('email', 'nieuw@acme.test')->first()?->notify_on_new_issue_email)->toBeTrue();
});

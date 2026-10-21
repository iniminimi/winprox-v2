<?php

use App\Livewire\Pages\Demo;
use App\Mail\DemoRequestConfirmationMail;
use App\Mail\DemoRequestOpsMail;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

it('toont de demo-pagina voor gasten in elke publieke taal', function () {
    foreach (config('locales.supported') as $locale) {
        $this->get(route('demo', ['locale' => $locale]))
            ->assertOk()
            ->assertSeeLivewire(Demo::class);
    }
});

it('staat in de sitemap', function () {
    $this->get(route('sitemap'))
        ->assertOk()
        ->assertSee(route('demo', ['locale' => 'nl'], absolute: true), false);
});

it('aanvaardt een geldige aanvraag en verstuurt beide mails', function () {
    Mail::fake();

    Livewire::test(Demo::class)
        ->set('company', 'Schoonmaak Jos')
        ->set('name', 'Jos Janssens')
        ->set('email', 'Jos@Example.com')
        ->set('phone', '+32 470 12 34 56')
        ->set('municipality', 'Gent')
        ->set('preference', 'video')
        ->set('note', 'Eerst graag uitleg over Checkmate.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSee(__('demo.success.title'));

    $stored = ContactMessage::query()->inbound()->latest('id')->first();
    expect($stored)->not->toBeNull()
        ->and($stored->email)->toBe('jos@example.com')
        ->and($stored->tenant_id)->toBeNull()
        ->and($stored->message)->toContain('Schoonmaak Jos')
        ->and($stored->message)->toContain('Gent');

    Mail::assertSent(DemoRequestOpsMail::class, function ($mail) {
        return $mail->hasTo((string) config('winprox.new_tenant_notification_email', 'info@winprox.app'));
    });

    Mail::assertSent(DemoRequestConfirmationMail::class, function ($mail) {
        return $mail->hasTo('jos@example.com') && $mail->preference === 'video';
    });
});

it('werkt zonder optionele toelichting', function () {
    Mail::fake();

    Livewire::test(Demo::class)
        ->set('company', 'Firma X')
        ->set('name', 'An Peeters')
        ->set('email', 'an@example.com')
        ->set('phone', '0470123456')
        ->set('municipality', 'Aalst')
        ->set('preference', 'onsite')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);
});

it('weigert ongeldige aanvragen', function () {
    Mail::fake();

    Livewire::test(Demo::class)
        ->set('company', '')
        ->set('name', '')
        ->set('email', 'geen-mail')
        ->set('phone', '1')
        ->set('municipality', '')
        ->set('preference', 'onsite')
        ->call('submit')
        ->assertHasErrors(['company', 'name', 'email', 'phone', 'municipality']);

    Mail::assertNothingSent();
    expect(ContactMessage::query()->count())->toBe(0);
});

it('vereist een geldige voorkeur', function () {
    Mail::fake();

    Livewire::test(Demo::class)
        ->set('company', 'Firma X')
        ->set('name', 'An')
        ->set('email', 'an@example.com')
        ->set('phone', '0470123456')
        ->set('municipality', 'Aalst')
        ->set('preference', 'carrier-pigeon')
        ->call('submit')
        ->assertHasErrors(['preference']);
});

it('verstuurt niets bij een tweede klik na succes', function () {
    Mail::fake();

    $component = Livewire::test(Demo::class)
        ->set('company', 'Firma X')
        ->set('name', 'An')
        ->set('email', 'an@example.com')
        ->set('phone', '0470123456')
        ->set('municipality', 'Aalst')
        ->call('submit')
        ->assertSet('submitted', true);

    $component->call('submit');

    Mail::assertSent(DemoRequestOpsMail::class, 1);
    Mail::assertSent(DemoRequestConfirmationMail::class, 1);
});

it('rate-limiteert herhaalde aanvragen vanaf hetzelfde ip', function () {
    Mail::fake();

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit('demo-request:hour:127.0.0.1', 3600);
    }

    Livewire::test(Demo::class)
        ->set('company', 'Firma X')
        ->set('name', 'An')
        ->set('email', 'an@example.com')
        ->set('phone', '0470123456')
        ->set('municipality', 'Aalst')
        ->call('submit')
        ->assertHasErrors(['form']);

    Mail::assertNothingSent();
});

it('laat registratie en checkmate ongemoeid', function () {
    $this->get(route('register', ['locale' => 'nl']))->assertOk();
    $this->get(route('checkmate', ['locale' => 'nl']))->assertOk();
    $this->get(route('pricing', ['locale' => 'nl']))->assertOk();
});

<?php

use App\Events\OutgoingMailBlockedByUnsubscribe;
use App\Listeners\AppendEmailUnsubscribeFooterToMessage;
use App\Listeners\BlockUnsubscribedEmailRecipients;
use App\Mail\WelcomeAccountMail;
use App\Models\EmailUnsubscribe;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;

uses()->group('email');

beforeEach(function () {
    Event::fake([
        OutgoingMailBlockedByUnsubscribe::class,
    ]);
});

describe('EmailUnsubscribe model', function () {
    it('normalizes email addresses to lowercase', function () {
        expect(EmailUnsubscribe::normalizeEmail('Test@Example.COM'))->toBe('test@example.com');
        expect(EmailUnsubscribe::normalizeEmail('  Test@Example.COM  '))->toBe('test@example.com');
    });

    it('can check if an email is unsubscribed', function () {
        expect(EmailUnsubscribe::isUnsubscribed('test@example.com'))->toBeFalse();

        EmailUnsubscribe::create([
            'email' => 'test@example.com',
            'unsubscribed_at' => now(),
        ]);

        expect(EmailUnsubscribe::isUnsubscribed('test@example.com'))->toBeTrue();
        expect(EmailUnsubscribe::isUnsubscribed('TEST@EXAMPLE.COM'))->toBeTrue();
        expect(EmailUnsubscribe::isUnsubscribed('other@example.com'))->toBeFalse();
    });
});

describe('BlockUnsubscribedEmailRecipients listener', function () {
    it('blocks emails to unsubscribed addresses', function () {
        EmailUnsubscribe::create([
            'email' => 'unsubscribed@example.com',
            'unsubscribed_at' => now(),
        ]);

        $message = new Email();
        $message->to('unsubscribed@example.com');
        $message->subject('Test');

        $event = new MessageSending($message);
        $listener = new BlockUnsubscribedEmailRecipients();

        $result = $listener->handle($event);

        expect($result)->toBeFalse();
        Event::assertDispatched(OutgoingMailBlockedByUnsubscribe::class, function ($event) {
            return in_array('unsubscribed@example.com', $event->unsubscribedAddresses);
        });
    });

    it('allows emails to subscribed addresses', function () {
        $message = new Email();
        $message->to('subscribed@example.com');
        $message->subject('Test');

        $event = new MessageSending($message);
        $listener = new BlockUnsubscribedEmailRecipients();

        $result = $listener->handle($event);

        expect($result)->toBeNull();
        Event::assertNotDispatched(OutgoingMailBlockedByUnsubscribe::class);
    });

    it('allows emails to exempt addresses even if in unsubscribe list', function () {
        config()->set('winprox.email_unsubscribe_exempt_recipients', ['exempt@example.com']);

        EmailUnsubscribe::create([
            'email' => 'exempt@example.com',
            'unsubscribed_at' => now(),
        ]);

        $message = new Email();
        $message->to('exempt@example.com');
        $message->subject('Test');

        $event = new MessageSending($message);
        $listener = new BlockUnsubscribedEmailRecipients();

        $result = $listener->handle($event);

        expect($result)->toBeNull();
        Event::assertNotDispatched(OutgoingMailBlockedByUnsubscribe::class);
    });
});

describe('AppendEmailUnsubscribeFooterToMessage listener', function () {
    it('adds list-unsubscribe header to emails', function () {
        $message = new Email();
        $message->to('test@example.com');
        $message->html('<body><p>Test</p></body>');

        $event = new MessageSending($message);
        $listener = new AppendEmailUnsubscribeFooterToMessage();

        $listener->handle($event);

        $headers = $message->getHeaders();
        expect($headers->has('List-Unsubscribe'))->toBeTrue();
    });

    it('adds unsubscribe footer to html emails', function () {
        $message = new Email();
        $message->to('test@example.com');
        $message->html('<body><p>Test</p></body>');

        $event = new MessageSending($message);
        $listener = new AppendEmailUnsubscribeFooterToMessage();

        $listener->handle($event);

        $html = $message->getHtmlBody();
        expect($html)->toContain('unsubscribe');

        // List-Unsubscribe is added as a header, not in the body
        $headers = $message->getHeaders();
        expect($headers->has('List-Unsubscribe'))->toBeTrue();
    });

    it('adds unsubscribe footer to text emails', function () {
        $message = new Email();
        $message->to('test@example.com');
        $message->text('Test message');

        $event = new MessageSending($message);
        $listener = new AppendEmailUnsubscribeFooterToMessage();

        $listener->handle($event);

        $text = $message->getTextBody();
        expect($text)->toContain('unsubscribe');
    });

    it('includes user settings hint for winprox users', function () {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
        ]);

        $message = new Email();
        $message->to('user@example.com');
        $message->html('<body><p>Test</p></body>');

        $event = new MessageSending($message);
        $listener = new AppendEmailUnsubscribeFooterToMessage();

        $listener->handle($event);

        $html = $message->getHtmlBody();
        expect($html)->toContain('Settings');
    });
});

describe('WelcomeAccountMail mailable', function () {
    it('builds with correct envelope', function () {
        $tenant = Tenant::factory()->create(['name' => 'Test Org']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Admin']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $mail = new WelcomeAccountMail(
            user: $user,
            tenant: $tenant,
            admin: $admin,
            resetToken: 'test-token',
        );

        $envelope = $mail->envelope();

        expect($envelope->subject)->toContain('Test Org');
    });

    it('includes reset url in content', function () {
        $tenant = Tenant::factory()->create(['name' => 'Test Org']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'role' => User::ROLE_EMPLOYEE,
        ]);

        $mail = new WelcomeAccountMail(
            user: $user,
            tenant: $tenant,
            admin: $admin,
            resetToken: 'test-token',
        );

        $content = $mail->content();

        expect($content->with['resetUrl'])->toContain('test-token');
        expect($content->with['resetUrl'])->toContain(urlencode('newuser@example.com'));
    });

    it('sends successfully via mail facade', function () {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'newuser@example.com',
        ]);

        $mail = new WelcomeAccountMail(
            user: $user,
            tenant: $tenant,
            admin: $admin,
            resetToken: 'test-token',
        );

        Mail::to('newuser@example.com')->send($mail);

        Mail::assertSent(WelcomeAccountMail::class, function ($mailable) {
            return $mailable->hasTo('newuser@example.com');
        });
    });
});

it('laat superuser e-mail uitschrijvingen beheren via platform', function () {
    $superuser = User::factory()->superuser()->create();

    Livewire::actingAs($superuser)
        ->test(\App\Livewire\Platform\EmailUnsubscribes::class)
        ->set('newEmail', 'block@example.com')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('block@example.com');

    expect(EmailUnsubscribe::isUnsubscribed('block@example.com'))->toBeTrue();

    $row = EmailUnsubscribe::query()->where('email', 'block@example.com')->firstOrFail();
    expect($row->source)->toBe(\App\Enums\EmailUnsubscribeSource::Manual);

    Livewire::actingAs($superuser)
        ->test(\App\Livewire\Platform\EmailUnsubscribes::class)
        ->assertSeeHtml('<strong>block@example.com</strong>')
        ->assertSee(mb_strtolower(__('platform.email_unsubscribe.source_manual'), 'UTF-8'), false)
        ->call('restore', $row->id)
        ->assertHasNoErrors();

    expect(EmailUnsubscribe::isUnsubscribed('block@example.com'))->toBeFalse();
});

it('filtert platform-uitschrijvingen op onbezorgbaar', function () {
    $superuser = User::factory()->superuser()->create();

    EmailUnsubscribe::query()->create([
        'email' => 'zelf@example.com',
        'source' => \App\Enums\EmailUnsubscribeSource::Voluntary,
        'unsubscribed_at' => now(),
    ]);
    EmailUnsubscribe::query()->create([
        'email' => 'bounce@example.com',
        'source' => \App\Enums\EmailUnsubscribeSource::Undeliverable,
        'unsubscribed_at' => now(),
    ]);
    EmailUnsubscribe::query()->create([
        'email' => 'handmatig@example.com',
        'source' => \App\Enums\EmailUnsubscribeSource::Manual,
        'unsubscribed_at' => now(),
    ]);

    Livewire::actingAs($superuser)
        ->test(\App\Livewire\Platform\EmailUnsubscribes::class)
        ->assertSee('zelf@example.com')
        ->assertSee('bounce@example.com')
        ->assertSee('handmatig@example.com')
        ->assertSee(__('platform.email_unsubscribe.filter_voluntary', ['count' => 1]))
        ->assertSee(__('platform.email_unsubscribe.filter_undeliverable', ['count' => 1]))
        ->assertSee(__('platform.email_unsubscribe.filter_manual', ['count' => 1]))
        ->set('undeliverableOnly', true)
        ->assertDontSee('zelf@example.com')
        ->assertDontSee('handmatig@example.com')
        ->assertSee('bounce@example.com')
        ->set('undeliverableOnly', false)
        ->set('manualOnly', true)
        ->assertDontSee('zelf@example.com')
        ->assertDontSee('bounce@example.com')
        ->assertSee('handmatig@example.com')
        ->set('manualOnly', false)
        ->set('voluntaryOnly', true)
        ->assertSee('zelf@example.com')
        ->assertDontSee('bounce@example.com')
        ->assertDontSee('handmatig@example.com')
        ->set('undeliverableOnly', true)
        ->assertSee('zelf@example.com')
        ->assertSee('bounce@example.com')
        ->assertDontSee('handmatig@example.com');
});

it('toont uitschrijvingen in superuser-menu in plaats van contactberichten', function () {
    $superuser = User::factory()->superuser()->create();

    $this->actingAs($superuser)
        ->get(route('platform.email-unsubscribes'))
        ->assertOk()
        ->assertSee(__('platform.email_unsubscribe.title'))
        ->assertSee(\App\Support\PageHelp::for('platform.email_unsubscribes')['title'], false);

    $this->actingAs($superuser)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(__('platform.email_unsubscribe.nav'))
        ->assertDontSee(__('platform.contact_messages.nav'), false);
});

it('purgeert Message-ID uitschrijvingen maar behoudt echte adressen', function () {
    EmailUnsubscribe::query()->create([
        'email' => 'lammering@trefoil.nl',
        'unsubscribed_at' => now(),
    ]);
    EmailUnsubscribe::query()->create([
        'email' => 'b126f9f96778abff0fbddf73cb42a975@winprox.app',
        'unsubscribed_at' => now(),
    ]);
    EmailUnsubscribe::query()->create([
        'email' => '178430534480.3659332.10843687058335425167@shared200.cloud86-host.io',
        'unsubscribed_at' => now(),
    ]);

    $result = app(\App\Actions\Marketing\PurgeBogusEmailUnsubscribesAction::class)->handle();

    expect($result['purged'])->toBe(2)
        ->and(EmailUnsubscribe::isUnsubscribed('lammering@trefoil.nl'))->toBeTrue()
        ->and(EmailUnsubscribe::isUnsubscribed('b126f9f96778abff0fbddf73cb42a975@winprox.app'))->toBeFalse()
        ->and(EmailUnsubscribe::isUnsubscribed(
            '178430534480.3659332.10843687058335425167@shared200.cloud86-host.io',
        ))->toBeFalse();
});

describe('Email unsubscribe confirmation route', function () {
    it('confirms unsubscribe with valid token', function () {
        $email = 'test@example.com';
        $token = Crypt::encryptString($email);

        $url = URL::signedRoute('email.unsubscribe', ['t' => $token]);

        expect(EmailUnsubscribe::isUnsubscribed($email))->toBeFalse();

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('email.unsubscribed');
        $response->assertViewHas('email', 'test@example.com');
        expect(EmailUnsubscribe::isUnsubscribed($email))->toBeTrue();
        expect(EmailUnsubscribe::query()->where('email', $email)->value('source'))
            ->toBe(\App\Enums\EmailUnsubscribeSource::Voluntary);
    });

    it('returns 403 for invalid token', function () {
        $response = $this->get('/email/unsubscribe?t=invalid');
        $response->assertForbidden();
    });

    it('returns 403 for missing token', function () {
        $response = $this->get('/email/unsubscribe');
        $response->assertForbidden();
    });

    it('detects if email belongs to existing user', function () {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.com',
        ]);

        $token = Crypt::encryptString('user@example.com');
        $url = URL::signedRoute('email.unsubscribe', ['t' => $token]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewHas('hasUser', true);
    });
});

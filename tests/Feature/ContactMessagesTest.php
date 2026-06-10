<?php

use App\Livewire\Platform\ContactMessages;
use App\Models\ContactMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(fn () => Tenancy::forget());
afterEach(fn () => Tenancy::forget());

it('can view contact messages page as superuser', function () {
    $superuser = User::factory()->superuser()->create();

    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->assertOk();
});

it('cannot access contact messages as regular user', function () {
    $user = User::factory()->create(['is_superuser' => false]);

    Livewire::actingAs($user)
        ->test(ContactMessages::class)
        ->assertForbidden();
});

it('displays messages with proper filtering', function () {
    $superuser = User::factory()->superuser()->create();

    $inboundSubjects = collect(['Inbound A', 'Inbound B', 'Inbound C'])->map(
        fn (string $subject) => ContactMessage::factory()->create([
            'direction' => 'inbound',
            'subject' => $subject,
        ])->subject,
    );

    $outboundSubjects = collect(['Outbound A', 'Outbound B'])->map(
        fn (string $subject) => ContactMessage::factory()->create([
            'direction' => 'outbound',
            'subject' => $subject,
            'read_at' => now(),
        ])->subject,
    );

    $component = Livewire::actingAs($superuser)
        ->test(ContactMessages::class);

    foreach ($inboundSubjects as $subject) {
        $component->assertSee($subject);
    }

    foreach ($outboundSubjects as $subject) {
        $component->assertDontSee($subject);
    }

    $component->set('filter', 'outbound');

    foreach ($outboundSubjects as $subject) {
        $component->assertSee($subject);
    }

    foreach ($inboundSubjects as $subject) {
        $component->assertDontSee($subject);
    }
});

it('can select and view message details', function () {
    $superuser = User::factory()->superuser()->create();
    $message = ContactMessage::factory()->create([
        'direction' => 'inbound',
        'subject' => 'Test Subject',
        'message' => 'Test message content',
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id)
        ->assertSee('Test Subject')
        ->assertSee('Test message content')
        ->assertSee('John Doe')
        ->assertSee('john@example.com');
});

it('marks inbound messages as read when selected', function () {
    $superuser = User::factory()->superuser()->create();
    $message = ContactMessage::factory()->create([
        'direction' => 'inbound',
        'read_at' => null,
    ]);

    expect($message->read_at)->toBeNull();

    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id);

    $message->refresh();
    expect($message->read_at)->not->toBeNull();
});

it('can send reply to inbound message', function () {
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    $tenant = Tenant::factory()->create();
    $message = ContactMessage::factory()->forTenant($tenant)->create([
        'direction' => 'inbound',
        'email' => 'test@example.com',
    ]);

    $replyText = 'This is a test reply';

    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id)
        ->set('reply', $replyText)
        ->call('sendReply')
        ->assertDispatched('reply-sent')
        ->assertSet('reply', '');

    $this->assertDatabaseHas('contact_messages', [
        'direction' => 'outbound',
        'message' => $replyText,
        'email' => config('mail.from.address'),
    ]);
});

it('validates reply input', function () {
    $superuser = User::factory()->superuser()->create();
    $message = ContactMessage::factory()->create(['direction' => 'inbound']);

    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id)
        ->set('reply', '')
        ->call('sendReply')
        ->assertHasErrors(['reply' => 'required']);
});

it('cannot reply to outbound messages', function () {
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    $message = ContactMessage::factory()->outbound()->create();

    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id)
        ->set('reply', 'Test reply')
        ->call('sendReply')
        ->assertNotDispatched('reply-sent');

    expect(ContactMessage::query()->where('direction', 'outbound')->count())->toBe(1);
});

it('filters messages by tenant when tenancy context is active', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $superuser = User::factory()->superuser()->create();

    $message1 = ContactMessage::factory()->forTenant($tenant1)->create([
        'subject' => 'Tenant one message',
    ]);

    $message2 = ContactMessage::factory()->forTenant($tenant2)->create([
        'subject' => 'Tenant two message',
    ]);

    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->assertSee($message1->subject)
        ->assertSee($message2->subject);

    Tenancy::actAs($tenant1->id);

    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->assertSee($message1->subject)
        ->assertDontSee($message2->subject);
});

it('updates unread count correctly', function () {
    $superuser = User::factory()->superuser()->create();

    ContactMessage::factory()->count(3)->inbound()->unread()->create();
    ContactMessage::factory()->inbound()->read()->create();

    $component = Livewire::actingAs($superuser)
        ->test(ContactMessages::class);

    $component->assertSee('3');

    $message = ContactMessage::query()->whereNull('read_at')->first();
    $component->call('selectMessage', $message->id);

    $component->assertSee('2');
});

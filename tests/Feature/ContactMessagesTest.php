<?php

use App\Actions\Contact\SendContactReplyAction;
use App\Livewire\Platform\ContactMessages;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

it('can view contact messages page as superuser', function () {
    $superuser = User::factory()->create(['is_superuser' => true]);
    
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
    $superuser = User::factory()->create(['is_superuser' => true]);
    
    // Create test messages
    ContactMessage::factory()->count(3)->create(['direction' => 'inbound']);
    ContactMessage::factory()->count(2)->create(['direction' => 'outbound']);
    
    $component = Livewire::actingAs($superuser)
        ->test(ContactMessages::class);
    
    // Test all filter (default)
    $component->assertSee('Contactberichten');
    $component->assertSeeText('5'); // total messages count
    
    // Test inbound filter
    $component->set('filter', 'inbound')
        ->assertSeeText('3');
    
    // Test outbound filter
    $component->set('filter', 'outbound')
        ->assertSeeText('2');
});

it('can select and view message details', function () {
    $superuser = User::factory()->create(['is_superuser' => true]);
    $message = ContactMessage::factory()->create([
        'direction' => 'inbound',
        'subject' => 'Test Subject',
        'message' => 'Test message content',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+31234567890'
    ]);
    
    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id)
        ->assertSee('Test Subject')
        ->assertSee('Test message content')
        ->assertSee('John Doe')
        ->assertSee('john@example.com')
        ->assertSee('+31234567890');
});

it('marks inbound messages as read when selected', function () {
    $superuser = User::factory()->create(['is_superuser' => true]);
    $message = ContactMessage::factory()->create([
        'direction' => 'inbound',
        'read_at' => null
    ]);
    
    expect($message->read_at)->toBeNull();
    
    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id);
    
    $message->refresh();
    expect($message->read_at)->not->toBeNull();
});

it('can send reply to inbound message', function () {
    $superuser = User::factory()->create(['is_superuser' => true]);
    $message = ContactMessage::factory()->create([
        'direction' => 'inbound',
        'email' => 'test@example.com'
    ]);
    
    $replyText = 'This is a test reply';
    
    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id)
        ->set('reply', $replyText)
        ->call('sendReply')
        ->assertDispatched('reply-sent')
        ->assertSet('reply', '');
    
    // Check that reply was saved
    $this->assertDatabaseHas('contact_messages', [
        'direction' => 'outbound',
        'message' => $replyText,
        'parent_id' => $message->id
    ]);
});

it('validates reply input', function () {
    $superuser = User::factory()->create(['is_superuser' => true]);
    $message = ContactMessage::factory()->create(['direction' => 'inbound']);
    
    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id)
        ->set('reply', '')
        ->call('sendReply')
        ->assertHasErrors(['reply' => 'required']);
});

it('cannot reply to outbound messages', function () {
    $superuser = User::factory()->create(['is_superuser' => true]);
    $message = ContactMessage::factory()->create(['direction' => 'outbound']);
    
    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->call('selectMessage', $message->id)
        ->set('reply', 'Test reply')
        ->call('sendReply')
        ->assertNotDispatched('reply-sent');
    
    // No reply should be created
    $this->assertDatabaseMissing('contact_messages', [
        'direction' => 'outbound',
        'parent_id' => $message->id
    ]);
});

it('handles tenant isolation correctly', function () {
    // Create two tenants
    $tenant1 = createTenant();
    $tenant2 = createTenant();
    
    // Create superuser
    $superuser = User::factory()->create(['is_superuser' => true]);
    
    // Create messages for different tenants
    $message1 = ContactMessage::factory()->create([
        'tenant_id' => $tenant1->id,
        'direction' => 'inbound'
    ]);
    
    $message2 = ContactMessage::factory()->create([
        'tenant_id' => $tenant2->id,
        'direction' => 'inbound'
    ]);
    
    // Test as superuser (should see all messages)
    Livewire::actingAs($superuser)
        ->test(ContactMessages::class)
        ->assertSee($message1->subject)
        ->assertSee($message2->subject);
    
    // Test as tenant1 user (should only see tenant1 messages)
    $tenant1User = User::factory()->create(['tenant_id' => $tenant1->id]);
    
    Tenancy::actAs($tenant1->id);
    
    Livewire::actingAs($tenant1User)
        ->test(ContactMessages::class)
        ->assertSee($message1->subject)
        ->assertDontSee($message2->subject);
});

it('updates unread count correctly', function () {
    $superuser = User::factory()->create(['is_superuser' => true]);
    
    // Create unread messages
    ContactMessage::factory()->count(3)->create([
        'direction' => 'inbound',
        'read_at' => null
    ]);
    
    // Create read message
    ContactMessage::factory()->create([
        'direction' => 'inbound',
        'read_at' => now()
    ]);
    
    $component = Livewire::actingAs($superuser)
        ->test(ContactMessages::class);
    
    // Should show 3 unread
    $component->assertSeeText('3');
    
    // Mark one as read
    $message = ContactMessage::whereNull('read_at')->first();
    $component->call('selectMessage', $message->id);
    
    // Should now show 2 unread
    $component->assertSeeText('2');
});

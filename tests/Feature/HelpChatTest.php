<?php

declare(strict_types=1);

use App\Livewire\Components\HelpChat;
use App\Mail\HelpChatEscalationToHelpdeskMail;
use App\Models\HelpChatUnansweredQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    Mail::fake();
    app()->setLocale('nl');
});

it('stuurt geen helpdesk-mail bij een onbeantwoorde vraag', function (): void {
    $user = User::factory()->admin()->create();
    $question = 'qzxwplkmn987654321';

    Livewire::actingAs($user)
        ->test(HelpChat::class)
        ->set('draft', $question)
        ->call('send')
        ->assertSet('escalationQuestion', $question);

    Mail::assertNothingSent();
    expect(HelpChatUnansweredQuestion::query()->count())->toBe(0);
});

it('stuurt de laatste onbeantwoorde vraag pas door na expliciete escalatie', function (): void {
    $user = User::factory()->admin()->create();
    $firstUnanswered = 'qzxwplkmn111122223333';
    $answered = 'reserveren';
    $secondUnanswered = 'qzxwplkmn444455556666';

    $component = Livewire::actingAs($user)
        ->test(HelpChat::class)
        ->set('draft', $firstUnanswered)
        ->call('send')
        ->set('draft', $answered)
        ->call('send')
        ->assertSet('escalationQuestion', $answered)
        ->set('draft', $secondUnanswered)
        ->call('send')
        ->assertSet('escalationQuestion', $secondUnanswered)
        ->call('escalateToHelpdesk')
        ->assertSet('escalationQuestion', null);

    Mail::assertSent(HelpChatEscalationToHelpdeskMail::class, 1);
    Mail::assertSent(HelpChatEscalationToHelpdeskMail::class, function (HelpChatEscalationToHelpdeskMail $mail) use ($user, $secondUnanswered): bool {
        return $mail->user->is($user)
            && $mail->question === $secondUnanswered
            && $mail->assistantReply === __('help.no_match');
    });

    expect(HelpChatUnansweredQuestion::query()->count())->toBe(1);
    expect(HelpChatUnansweredQuestion::query()->value('question'))->toBe($secondUnanswered);
});

it('toont de doorstuur-knop na elk assistent-antwoord', function (): void {
    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(HelpChat::class)
        ->assertDontSee(__('help.escalate'))
        ->set('draft', 'qzxwplkmn777788889999')
        ->call('send')
        ->assertSee(__('help.escalate'))
        ->set('draft', 'reserveren')
        ->call('send')
        ->assertSee(__('help.escalate'));
});

it('stuurt een beantwoorde vraag door met het assistent-antwoord', function (): void {
    $user = User::factory()->admin()->create();
    $question = 'reserveren';

    Livewire::actingAs($user)
        ->test(HelpChat::class)
        ->set('draft', $question)
        ->call('send')
        ->call('escalateToHelpdesk');

    Mail::assertSent(HelpChatEscalationToHelpdeskMail::class, function (HelpChatEscalationToHelpdeskMail $mail) use ($user, $question): bool {
        return $mail->user->is($user)
            && $mail->question === $question
            && is_string($mail->assistantReply)
            && $mail->assistantReply !== __('help.no_match');
    });

    expect(HelpChatUnansweredQuestion::query()->count())->toBe(1);
    expect(HelpChatUnansweredQuestion::query()->value('question'))->toBe($question);
});

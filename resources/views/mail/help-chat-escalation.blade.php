{{ __('help.mail.escalation_body', [
    'user' => $user->name,
    'email' => $user->email,
    'tenant' => $user->tenant?->name ?? '—',
    'question' => $question,
    'reply' => $assistantReply ?? '—',
]) }}

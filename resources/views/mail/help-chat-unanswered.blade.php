{{ __('help.mail.unanswered_body', [
    'user' => $user->name,
    'email' => $user->email,
    'tenant' => $user->tenant?->name ?? '—',
    'question' => $question,
]) }}

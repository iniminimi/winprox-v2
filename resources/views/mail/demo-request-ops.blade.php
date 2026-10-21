{{ __('mail.demo_request.ops_body', [
    'company' => $data['company'],
    'name' => $data['name'],
    'email' => $data['email'],
    'phone' => $data['phone'],
    'municipality' => $data['municipality'],
    'preference' => $preferenceLabel,
    'note' => filled($data['note'] ?? null) ? $data['note'] : '—',
]) }}

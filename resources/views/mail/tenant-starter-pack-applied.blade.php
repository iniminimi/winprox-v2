{{ __('mail.starter_pack.body', [
    'tenant' => $tenant->name,
    'admin' => $actor->name,
    'email' => $actor->email,
    'pack_type' => $packType,
    'tenant_id' => $tenant->id,
]) }}

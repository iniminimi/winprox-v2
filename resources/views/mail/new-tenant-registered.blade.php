{{ __('mail.new_tenant.body', [
    'tenant' => $tenant->name,
    'admin' => $admin->name,
    'email' => $admin->email,
    'phone' => $phone,
    'address' => $address,
    'country' => $country,
    'tenant_id' => $tenant->id,
]) }}

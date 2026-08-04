<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterTenantAction;
use App\Http\Requests\Auth\RegisterRequest;
use App\Support\CountryOptions;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('WinProx')]
class Register extends Component
{
    public string $organization = '';

    public string $phone = '';

    public string $street = '';

    public string $house_number = '';

    public string $postal_code = '';

    public string $city = '';

    public string $country_code = 'BE';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_terms = false;

    public function register(RegisterTenantAction $registerTenant)
    {
        $this->country_code = strtoupper(trim($this->country_code));

        $request = new RegisterRequest;

        $validated = $this->validate($request->rules(), $request->messages());
        $validated['locale'] = app()->getLocale();

        $user = $registerTenant->handle($validated);

        Auth::login($user);

        session()->regenerate();
        session()->flash('register_success', true);

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register', [
            'countries' => CountryOptions::selectOptions(),
        ]);
    }
}

<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterTenantAction;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('WinProx')]
class Register extends Component
{
    public string $organization = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(RegisterTenantAction $registerTenant)
    {
        $request = new RegisterRequest;

        $validated = $this->validate($request->rules(), $request->messages());

        $user = $registerTenant->handle($validated);

        Auth::login($user);

        session()->regenerate();

        return $this->redirectRoute('dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}

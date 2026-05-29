<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Team\TeamQrController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Dashboard;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Issues\Index as IssueIndex;
use App\Livewire\Issues\Show as IssueShow;
use App\Livewire\Pages\Calendar;
use App\Livewire\Pages\Contact;
use App\Livewire\Pages\Faq;
use App\Livewire\Pages\Legal;
use App\Livewire\Pages\Locations;
use App\Livewire\Pages\Subscription;
use App\Livewire\Pages\Tasks;
use App\Livewire\Pages\Team;
use App\Livewire\Public\TeamPortal;
use App\Livewire\Public\UnitPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

// Talenkeuze: bewaart de locale in de sessie (gast én ingelogd).
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

// Publieke QR-schermen (geen auth) — mobiel-first.
Route::get('/melden/{token}', UnitPortal::class)->name('public.unit-portal');
Route::get('/team/{token}', TeamPortal::class)->name('public.team-portal');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/issues', IssueIndex::class)->name('issues.index');
    Route::get('/issues/create', IssueCreate::class)->name('issues.create');
    Route::get('/issues/{issue}', IssueShow::class)->name('issues.show');

    // Beheers-stubs (navigatie werkt; volledige features volgen later).
    Route::get('/locations', Locations::class)->name('locations.index');
    Route::get('/tasks', Tasks::class)->name('tasks.index');
    Route::get('/calendar', Calendar::class)->name('calendar.index');
    Route::get('/team', Team::class)->name('team.index');
    Route::get('/team/{team}/qr', TeamQrController::class)->name('team.qr');
    Route::get('/subscription', Subscription::class)->name('subscription.index');
    Route::get('/faq', Faq::class)->name('faq.index');
    Route::get('/legal', Legal::class)->name('legal.index');
    Route::get('/contact', Contact::class)->name('contact.index');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Issues\Create as IssueCreate;
use App\Livewire\Issues\Index as IssueIndex;
use App\Livewire\Issues\Show as IssueShow;
use App\Livewire\Public\FieldPortal;
use App\Livewire\Public\Report;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

// Publieke QR-schermen (geen auth) — mobiel-first.
Route::get('/melden/{token}', Report::class)->name('public.report');
Route::get('/team/{token}', FieldPortal::class)->name('public.field-portal');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/issues', IssueIndex::class)->name('issues.index');
    Route::get('/issues/create', IssueCreate::class)->name('issues.create');
    Route::get('/issues/{issue}', IssueShow::class)->name('issues.show');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

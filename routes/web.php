<?php

use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\BriefingPrintController;
use App\Http\Controllers\EmailUnsubscribeController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UiThemeController;
use App\Http\Controllers\UserDataExportController;
use App\Http\Controllers\TenantPurgeConfirmController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\PromoQrDownloadController;
use App\Http\Controllers\PromoRecipientQrDownloadController;
use App\Http\Controllers\PromoVideoTrackController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\Locations\LocationQrPackDownloadController;
use App\Http\Controllers\Locations\UnitQrController;
use App\Http\Controllers\Time\ClockPointQrController;
use App\Http\Controllers\Time\WorkShiftExportController;
use App\Http\Controllers\Time\WorkShiftTimesheetPrintController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Dashboard;
use App\Livewire\Esg\Dashboard as EsgDashboard;
use App\Livewire\Esg\IndicatorsIndex as EsgIndicatorsIndex;
use App\Livewire\Esg\MeasurementsIndex as EsgMeasurementsIndex;
use App\Livewire\Esg\PointHistory as EsgPointHistory;
use App\Livewire\Iot\Index as IotIndex;
use App\Livewire\Issues\Index as IssueIndex;
use App\Livewire\Issues\Show as IssueShow;
use App\Livewire\Locations\Index as LocationIndex;
use App\Livewire\Locations\Show as LocationShow;
use App\Livewire\Pages\About;
use App\Livewire\Pages\Calendar;
use App\Livewire\Pages\ApiDocumentation;
use App\Livewire\Pages\ApiSettings;
use App\Livewire\Pages\Contact;
use App\Livewire\Pages\Faq;
use App\Livewire\Pages\FeaturePage;
use App\Livewire\Pages\Health;
use App\Livewire\Pages\Legal;
use App\Livewire\Pages\ManualHub;
use App\Livewire\Pages\ManualIndex;
use App\Livewire\Pages\TeamleaderManualIndex;
use App\Livewire\Pages\WorkerManualIndex;
use App\Livewire\Pages\Pricing;
use App\Livewire\Pages\PublicApi;
use App\Livewire\Pages\PublicFaq;
use App\Livewire\Pages\Settings;
use App\Livewire\Pages\Subscription;
use App\Livewire\Pages\Team;
use App\Livewire\Platform\Audit as PlatformAudit;
use App\Livewire\Platform\EmailUnsubscribes;
use App\Livewire\Platform\ContactMessages;
use App\Livewire\Platform\Dashboard as PlatformDashboard;
use App\Livewire\Platform\Help as PlatformHelp;
use App\Livewire\Platform\ManualScreenshots as PlatformManualScreenshots;
use App\Livewire\Platform\PromoCampaignEdit;
use App\Livewire\Platform\PromoCampaigns;
use App\Livewire\Platform\PromoRecipients as PlatformPromoRecipients;
use App\Livewire\Platform\TranslationSync as PlatformTranslationSync;
use App\Livewire\Platform\QrConnect;
use App\Livewire\Platform\Tenants as PlatformTenants;
use App\Livewire\Platform\Users as PlatformUsers;
use App\Livewire\Tasks\Index as TaskIndex;
use App\Support\Platform\SupportTenantContext;
use App\Support\ResolveAppLocale;
use App\Livewire\Tasks\Show as TaskShow;
use App\Livewire\Time\AlarmsIndex;
use App\Livewire\Time\ClockPointsIndex;
use App\Livewire\Time\PresenceIndex;
use App\Livewire\Time\ShiftsIndex;
use App\Livewire\Public\ReservationConfirm;
use App\Livewire\Public\ReservationManage;
use App\Livewire\Public\TimePortal;
use App\Livewire\Public\UnassignedQrPortal;
use App\Livewire\Public\UnitPortal;
use App\Livewire\Pages\ReservationsIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$localePattern = implode('|', config('locales.supported', ['nl']));

Route::get('/welcome-1995', function () {
    return response()
        ->view('welcome-1995')
        ->header('X-Robots-Tag', 'noindex, nofollow');
})->name('welcome.classic');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/llms.txt', LlmsTxtController::class)->name('llms.txt');

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::get('/q/{token}', QrController::class)->name('qr.scan');

Route::get('/melden/{token}', UnitPortal::class)->name('public.unit-portal');
Route::get('/melden/onbekend/{token}', UnassignedQrPortal::class)->name('public.unassigned-qr-portal');
Route::get('/reservations/confirm/{token}', ReservationConfirm::class)->name('reservations.confirm');
Route::get('/reservations/manage/{token}', ReservationManage::class)->name('reservations.manage');

Route::get('/time/{token}', TimePortal::class)
    ->where('token', '[a-z0-9]{20,64}')
    ->name('public.time-portal');

Route::get('/email/unsubscribe', [EmailUnsubscribeController::class, 'confirm'])
    ->middleware('signed')
    ->name('email.unsubscribe');

Route::get('/email/resubscribe', [EmailUnsubscribeController::class, 'resubscribe'])
    ->middleware('signed')
    ->name('email.resubscribe');

/*
|--------------------------------------------------------------------------
| Marketing (unieke URL per taal) + legacy redirects zonder locale-prefix
|--------------------------------------------------------------------------
*/

$redirectToLocalized = static function (string $routeName) {
    return static function (Request $request) use ($routeName) {
        $supported = config('locales.supported', []);
        $locale = null;
        $lang = $request->query('lang');
        if (is_string($lang)) {
            $lang = strtolower(trim($lang));
            if (in_array($lang, $supported, true)) {
                $locale = $lang;
            }
        }
        $locale ??= ResolveAppLocale::resolve($request);

        $query = $request->query();
        unset($query['lang']);

        return redirect()->route($routeName, array_merge($query, ['locale' => $locale]));
    };
};

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->is_superuser && $user->tenant_id === null && ! SupportTenantContext::isActive()) {
            return redirect()->route('platform.tenants');
        }

        return redirect()->route('dashboard');
    }

    $locale = ResolveAppLocale::resolve(request());

    return redirect()->route('welcome', ['locale' => $locale]);
})->name('home');

Route::get('/promo', $redirectToLocalized('promo'));
Route::get('/pricing', $redirectToLocalized('pricing'));
Route::get('/contact', $redirectToLocalized('contact.index'));
Route::get('/about', $redirectToLocalized('about'));
Route::get('/api', $redirectToLocalized('api.public'));
Route::get('/features/facility', $redirectToLocalized('features.facility'));
Route::get('/features/time', $redirectToLocalized('features.time'));
Route::get('/features/esg', $redirectToLocalized('features.esg'));
Route::get('/features/iot', $redirectToLocalized('features.iot'));
Route::get('/features/qr-portals', $redirectToLocalized('features.qr'));

foreach (config('legal.documents', []) as $legalDoc => $legalMeta) {
    Route::get("/legal/{$legalDoc}", $redirectToLocalized($legalMeta['route']));
}

Route::prefix('{locale}')
    ->where(['locale' => $localePattern])
    ->group(function () {
        Route::get('/', WelcomeController::class)->name('welcome');

        Route::get('/contact', Contact::class)->name('contact.index');
        Route::get('/pricing', Pricing::class)->name('pricing');
        Route::get('/faq', PublicFaq::class)->name('faq.public');
        Route::get('/about', About::class)->name('about');
        Route::get('/api', PublicApi::class)->name('api.public');
        Route::get('/features/facility', FeaturePage::class)->name('features.facility');
        Route::get('/features/time', FeaturePage::class)->name('features.time');
        Route::get('/features/esg', FeaturePage::class)->name('features.esg');
        Route::get('/features/iot', FeaturePage::class)->name('features.iot');
        Route::get('/features/qr-portals', FeaturePage::class)->name('features.qr');
        Route::get('/promo', [PromoController::class, 'show'])->name('promo');

        foreach (config('legal.documents', []) as $legalDoc => $legalMeta) {
            Route::get("/legal/{$legalDoc}", function () use ($legalDoc) {
                return app(LegalDocumentController::class)->show(request(), $legalDoc);
            })->name($legalMeta['route']);
        }
    });

Route::post('/promo/track/video', PromoVideoTrackController::class)
    ->middleware('throttle:60,1')
    ->name('promo.track.video');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/ui-theme/{theme}', UiThemeController::class)->name('ui-theme.switch');

    Route::get('/platform', PlatformDashboard::class)
        ->middleware('superuser')
        ->name('platform.dashboard');
    Route::get('/platform/tenants', PlatformTenants::class)
        ->middleware('superuser')
        ->name('platform.tenants');
    Route::get('/platform/users', PlatformUsers::class)
        ->middleware('superuser')
        ->name('platform.users');
    Route::get('/platform/audit', PlatformAudit::class)
        ->middleware('superuser')
        ->name('platform.audit');
    Route::get('/platform/help', PlatformHelp::class)
        ->middleware('superuser')
        ->name('platform.help');
    Route::get('/platform/email-unsubscribes', EmailUnsubscribes::class)
        ->middleware('superuser')
        ->name('platform.email-unsubscribes');
    Route::get('/platform/contact-messages', ContactMessages::class)
        ->middleware('superuser')
        ->name('platform.contact-messages');
    Route::get('/platform/screenshots', PlatformManualScreenshots::class)
        ->middleware('superuser')
        ->name('platform.screenshots');
    Route::get('/platform/translations', PlatformTranslationSync::class)
        ->middleware('superuser')
        ->name('platform.translations');

    Route::get('/platform/promo-qr/download', PromoQrDownloadController::class)
        ->middleware('superuser')
        ->name('platform.promo-qr.download');

    Route::get('/platform/promo-campaigns', PromoCampaigns::class)
        ->middleware('superuser')
        ->name('platform.promo-campaigns');

    Route::get('/platform/promo-campaigns/{promoCampaign}', PromoCampaignEdit::class)
        ->middleware('superuser')
        ->name('platform.promo-campaigns.edit');

    Route::get('/platform/promo-recipients', PlatformPromoRecipients::class)
        ->middleware('superuser')
        ->name('platform.promo-recipients');

    Route::get('/platform/promo-recipients/{promoRecipient}/qr', PromoRecipientQrDownloadController::class)
        ->middleware('superuser')
        ->name('platform.promo-recipients.qr');

    Route::get('/faq', Faq::class)->name('faq.index');
    Route::get('/legal', Legal::class)->name('legal.index');
    Route::get('/manual', ManualHub::class)->name('manual.hub');
    Route::get('/manual/general', ManualIndex::class)->name('manual.general');
    Route::get('/manual/workers', WorkerManualIndex::class)->name('manual.workers');
    Route::get('/manual/teamleaders', TeamleaderManualIndex::class)->name('manual.teamleaders');
    Route::get('/account/data-export', UserDataExportController::class)->name('account.data-export');
    Route::get('/subscription/purge/confirm/{purgeRequest}/{token}', TenantPurgeConfirmController::class)
        ->name('subscription.purge.confirm');

    Route::middleware('support.tenant')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/health', Health::class)->name('health.index');

        Route::get('/issues', IssueIndex::class)->name('issues.index');
        Route::get('/issues/create', fn () => redirect()->route('issues.index', ['create' => 1]))->name('issues.create');
        Route::get('/issues/{issue}', IssueShow::class)->name('issues.show');

        Route::get('/locations', LocationIndex::class)->name('locations.index');
        Route::get('/locations/{location}', LocationShow::class)->name('locations.show');
        Route::get('/locations/{location}/qr-pack', LocationQrPackDownloadController::class)->name('locations.qr-pack');
        Route::get('/units/{unit}/qr', UnitQrController::class)->name('units.qr');
        Route::get('/briefing/print', BriefingPrintController::class)->name('briefing.print');
        Route::get('/tasks', TaskIndex::class)->name('tasks.index');
        Route::get('/tasks/{task}', TaskShow::class)->name('tasks.show');
        Route::get('/calendar', Calendar::class)->name('calendar.index');
        Route::get('/reservations', ReservationsIndex::class)->name('reservations.index');
        Route::get('/esg', EsgDashboard::class)->name('esg.dashboard');
        Route::get('/esg/indicators', EsgIndicatorsIndex::class)->name('esg.indicators.index');
        Route::get('/esg/measurements', EsgMeasurementsIndex::class)->name('esg.measurements.index');
        Route::get('/esg/point', EsgPointHistory::class)->name('esg.point.history');
        Route::get('/iot', IotIndex::class)->name('iot.index');
        Route::get('/time/presence', PresenceIndex::class)->name('time.presence.index');
        Route::get('/time/alarms', AlarmsIndex::class)->name('time.alarms.index');
        Route::get('/time/shifts', ShiftsIndex::class)->name('time.shifts.index');
        Route::get('/time/shifts/export', WorkShiftExportController::class)->name('time.shifts.export');
        Route::get('/time/shifts/print', WorkShiftTimesheetPrintController::class)->name('time.shifts.print');
        Route::get('/time/clock-points', ClockPointsIndex::class)->name('time.clock-points.index');
        Route::get('/time/clock-points/{clockPoint}/qr', ClockPointQrController::class)->name('time.clock-points.qr');
        Route::get('/team', Team::class)->name('team.index');
        Route::get('/settings', Settings::class)->name('settings.index');
        Route::get('/settings/api', ApiSettings::class)->name('settings.api');
        Route::get('/settings/api/docs', ApiDocumentation::class)->name('settings.api.docs');
        Route::get('/settings/api/docs/{file}', ApiDocumentation::class)->name('settings.api.docs.show');
        Route::get('/subscription', Subscription::class)->name('subscription.index');
        Route::get('/qr/connect/{token}', QrConnect::class)->name('qr.connect');
    });

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

/*
|--------------------------------------------------------------------------
| SuperUser Email Unsubscribe Management (OPERATIONAL - Override approved)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'superuser'])->group(function () {
    Route::get('/admin/email-unsubscribes', function () {
        return redirect()->route('platform.email-unsubscribes');
    })->name('admin.email-unsubscribes.index');
});

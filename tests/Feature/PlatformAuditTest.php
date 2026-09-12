<?php

declare(strict_types=1);

use App\Actions\Audit\ListPlatformAuditLogsAction;
use App\Actions\Audit\LogAuditAction;
use App\Livewire\Platform\Audit;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\SummarizeAuditLog;
use App\Support\PageHelp;
use Livewire\Livewire;

it('vat auditregels samen in gewone taal', function () {
    app()->setLocale('nl');

    $tenant = Tenant::factory()->create(['name' => 'Demo Org']);
    $user = User::factory()->for($tenant)->create(['name' => 'Ada Admin', 'email' => 'ada@example.com']);

    $log = app(LogAuditAction::class)->handle(
        userId: $user->id,
        tenantId: $tenant->id,
        action: 'issue.approved',
        modelType: 'Issue',
        modelId: 42,
        payload: ['description' => 'Kapotte kraan'],
    );

    $summary = app(SummarizeAuditLog::class)->handle($log->fresh(['tenant', 'user']));

    expect($summary['title'])->toBe('Melding goedgekeurd')
        ->and($summary['meta'])->toContain('Demo Org')
        ->and($summary['meta'])->toContain('Ada Admin')
        ->and($summary['context'])->toContain('#42')
        ->and($summary['context'])->toContain('Kapotte kraan');
});

it('toont menselijke platformaudit met pagina-hulp', function () {
    app()->setLocale('nl');

    $superuser = User::factory()->superuser()->create();
    app(LogAuditAction::class)->handle(
        userId: $superuser->id,
        tenantId: null,
        action: 'marketing.promo_campaign_created',
        modelType: 'PromoCampaign',
        modelId: 7,
        payload: ['name' => 'Havens Wave', 'slug' => 'havens-wave-1'],
    );

    Livewire::actingAs($superuser)
        ->test(Audit::class)
        ->assertSee(__('platform.audit.title'))
        ->assertSee(__('page-help.button_label'))
        ->assertSee('Promo-campagne aangemaakt')
        ->assertSee('Havens Wave')
        ->assertDontSee('marketing.promo_campaign_created');

    expect(PageHelp::for('platform.audit'))->not->toBeNull()
        ->and(PageHelp::for('platform.audit')['title'])->toBe('Hulp — Activiteitenlog');
});

it('zoekt auditregels op menselijke actielabel', function () {
    app()->setLocale('nl');

    $superuser = User::factory()->superuser()->create();
    app(LogAuditAction::class)->handle(
        userId: $superuser->id,
        tenantId: null,
        action: 'email.unsubscribed',
        modelType: 'EmailUnsubscribe',
        modelId: 1,
        payload: ['email' => 'klant@example.com'],
    );

    $result = app(ListPlatformAuditLogsAction::class)->handle('uitgeschreven', 1, 20);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['summaries']->first()['title'])->toBe('E-mail uitgeschreven');
});

it('verbergt automatische promo-pauze-vrijgave in het activiteitenlog', function () {
    app()->setLocale('nl');

    $superuser = User::factory()->superuser()->create();
    app(LogAuditAction::class)->handle(
        userId: null,
        tenantId: null,
        action: 'marketing.promo_campaign_pause_released_complete',
        modelType: 'PromoCampaign',
        modelId: 3,
        payload: ['automatic' => true],
    );
    app(LogAuditAction::class)->handle(
        userId: $superuser->id,
        tenantId: null,
        action: 'marketing.promo_campaign_created',
        modelType: 'PromoCampaign',
        modelId: 3,
        payload: ['name' => 'Havens Wave'],
    );

    $result = app(ListPlatformAuditLogsAction::class)->handle('', 1, 20);
    $recent = app(ListPlatformAuditLogsAction::class)->recentSummaries(8);

    expect($result['rows']->pluck('action')->all())
        ->not->toContain('marketing.promo_campaign_pause_released_complete')
        ->toContain('marketing.promo_campaign_created')
        ->and(collect($recent)->pluck('action')->all())
        ->not->toContain('marketing.promo_campaign_pause_released_complete')
        ->toContain('marketing.promo_campaign_created');

    Livewire::actingAs($superuser)
        ->test(Audit::class)
        ->assertSee('Promo-campagne aangemaakt')
        ->assertDontSee('Systeemactie')
        ->assertDontSee('promo campaign pause released');
});

it('toont recente activiteiten op het platform-dashboard', function () {
    app()->setLocale('nl');

    $superuser = User::factory()->superuser()->create();
    app(LogAuditAction::class)->handle(
        userId: $superuser->id,
        tenantId: null,
        action: 'issue.approved',
        modelType: 'Issue',
        modelId: 9,
        payload: null,
    );

    $this->actingAs($superuser)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(__('platform.dashboard.recent_audit'))
        ->assertSee('Melding goedgekeurd');
});

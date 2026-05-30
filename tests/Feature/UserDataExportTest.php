<?php

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('laat ingelogde gebruiker een json gdpr-export downloaden', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $issue = Issue::factory()->create([
        'tenant_id' => $tenant->id,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('account.data-export'));

    $response->assertOk()
        ->assertHeader('content-disposition');

    $json = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($json['schema_version'])->toBe(1)
        ->and($json['user']['email'])->toBe($user->email)
        ->and($json['user'])->not->toHaveKey('password')
        ->and($json['tenant']['id'])->toBe($tenant->id)
        ->and($json['issues_approved'])->toHaveCount(1)
        ->and($json['issues_approved'][0]['id'])->toBe($issue->id);

    expect(AuditLog::query()->where('action', 'gdpr.data_exported')->where('user_id', $user->id)->exists())
        ->toBeTrue();
});

it('weigert gdpr-export voor gasten', function () {
    $this->get(route('account.data-export'))
        ->assertRedirect(route('login'));
});

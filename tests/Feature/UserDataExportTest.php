<?php

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('laat ingelogde gebruiker een zip gdpr-export downloaden met json', function () {
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
        ->assertDownload()
        ->assertHeader('content-type', 'application/zip');

    $zip = new \ZipArchive;
    $tmp = tempnam(sys_get_temp_dir(), 'wp-gdpr-export-');
    file_put_contents($tmp, $response->streamedContent());
    expect($zip->open($tmp))->toBeTrue();

    $jsonName = sprintf('winprox-data-export-%d-%s.json', $user->id, now()->format('Y-m-d'));
    $json = $zip->getFromName($jsonName);
    $zip->close();
    @unlink($tmp);

    expect($json)->toBeString();

    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    expect($payload['schema_version'])->toBe(1)
        ->and($payload['user']['email'])->toBe($user->email)
        ->and($payload['user'])->not->toHaveKey('password')
        ->and($payload['tenant']['id'])->toBe($tenant->id)
        ->and($payload['issues_approved'])->toHaveCount(1)
        ->and($payload['issues_approved'][0]['id'])->toBe($issue->id);

    expect(AuditLog::query()->where('action', 'gdpr.data_exported')->where('user_id', $user->id)->exists())
        ->toBeTrue();
});

it('weigert gdpr-export voor gasten', function () {
    $this->get(route('account.data-export'))
        ->assertRedirect(route('login'));
});

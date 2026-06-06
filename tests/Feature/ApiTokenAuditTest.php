<?php

use App\Actions\Api\CreateApiTokenAction;
use App\Actions\Api\RevokeApiTokenAction;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('schrijft audit bij API-token aanmaken en intrekken', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    $plain = app(CreateApiTokenAction::class)->handle($admin, 'test-token', [], $admin->id);
    expect($plain)->not->toBeEmpty();

    $created = AuditLog::query()->where('action', 'api_token.created')->latest('id')->first();
    expect($created)->not->toBeNull()
        ->and($created->user_id)->toBe($admin->id);

    $tokenId = (int) PersonalAccessToken::query()->where('tokenable_id', $admin->id)->value('id');

    app(RevokeApiTokenAction::class)->handle($admin, $tokenId, $admin->id);

    expect(AuditLog::query()->where('action', 'api_token.revoked')->exists())->toBeTrue();
});

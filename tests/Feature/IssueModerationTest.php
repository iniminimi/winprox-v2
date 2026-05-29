<?php

use App\Actions\Issues\ApproveIssueAction;
use App\Models\Issue;
use App\Models\IssuePhoto;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('is niet goedgekeurd bij aanmaken (inhoud blijft geblurd)', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);

    expect($issue->isApproved())->toBeFalse()
        ->and($issue->approved_at)->toBeNull();
});

it('keurt een melding goed via ApproveIssueAction', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $reviewer = User::factory()->create(['tenant_id' => $tenant->id]);

    app(ApproveIssueAction::class)->handle($issue, $reviewer);

    expect($issue->fresh()->isApproved())->toBeTrue()
        ->and($issue->fresh()->approved_by)->toBe($reviewer->id);
});

it('koppelt foto’s aan een melding', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    IssuePhoto::factory()->count(4)->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
    ]);

    expect($issue->photos()->count())->toBe(4);
});

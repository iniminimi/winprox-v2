<?php

use App\Actions\Issues\CreateIssueUpdateAction;
use App\Livewire\Issues\Show;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('voegt een notitie toe op meldingdetail via livewire', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);

    Tenancy::actAs($tenant->id);

    Livewire::actingAs($user)
        ->test(Show::class, ['issue' => $issue])
        ->set('updateBody', 'Extra info voor het team')
        ->call('saveUpdate')
        ->assertHasNoErrors();

    $issue->refresh();
    $update = $issue->updates()->first();

    expect($update)->not->toBeNull()
        ->and($update->body)->toBe('Extra info voor het team')
        ->and($update->user_id)->toBe($user->id);

    expect(AuditLog::query()->where('action', 'issue.update_added')->exists())->toBeTrue();
});

it('registreert een notitie met foto via de action', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);

    Tenancy::actAs($tenant->id);

    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A0AAA/9k=',
        true,
    );
    $file = UploadedFile::fake()->createWithContent('note.jpg', $jpeg, 'image/jpeg');

    app(CreateIssueUpdateAction::class)->handle(
        $issue,
        $user,
        'Korte notitie',
        [$file],
    );

    $update = $issue->updates()->first();

    expect($update)->not->toBeNull()
        ->and($update->photos)->toHaveCount(1);
});

it('toont een update-foto op meldingdetail na opslaan via livewire', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => now()]);

    Tenancy::actAs($tenant->id);

    $jpeg = base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A0AAA/9k=',
        true,
    );
    $file = UploadedFile::fake()->createWithContent('update.jpg', $jpeg, 'image/jpeg');

    Livewire::actingAs($user)
        ->test(Show::class, ['issue' => $issue])
        ->set('updateBody', 'Zie bijlage')
        ->set('updatePhotos', [$file])
        ->call('saveUpdate')
        ->assertHasNoErrors();

    $photo = $issue->fresh()->photos->first();

    expect($photo)->not->toBeNull()
        ->and(Storage::disk('public')->exists($photo->path))->toBeTrue()
        ->and($photo->publicUrl())->toBe('/storage/'.$photo->path);

    Livewire::actingAs($user)
        ->test(Show::class, ['issue' => $issue->fresh()])
        ->assertSee('/storage/'.$photo->path, false);
});

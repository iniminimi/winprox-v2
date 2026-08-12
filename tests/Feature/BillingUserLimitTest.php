<?php

namespace Tests\Feature;

use App\Actions\Team\CreateColleagueAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingUserLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_colleague_succeeds_without_limit(): void
    {
        // Gebruikers zijn onbeperkt in alle tiers.
        Notification::fake();

        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        User::factory()->admin()->for($tenant)->create();
        User::factory()->employee()->count(10)->for($tenant)->create();

        $user = app(CreateColleagueAction::class)->handle([
            'name' => 'Twaalfde',
            'email' => 'twaalf@example.test',
            'locale' => 'nl',
            'role' => User::ROLE_EMPLOYEE,
            'password' => 'wachtwoord123',
            'send_account_email' => false,
        ], $tenant->id);

        $this->assertSame('twaalf@example.test', $user->email);
        $this->assertNull($tenant->fresh()->maxUsersLimit());
    }
}

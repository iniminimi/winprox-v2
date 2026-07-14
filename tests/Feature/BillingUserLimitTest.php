<?php

namespace Tests\Feature;

use App\Actions\Team\CreateColleagueAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class BillingUserLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_colleague_throws_when_plan_limit_reached(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        User::factory()->admin()->for($tenant)->create();
        User::factory()->employee()->count(2)->for($tenant)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('user_limit_exceeded');

        app(CreateColleagueAction::class)->handle([
            'name' => 'Zevende',
            'email' => 'zeven@example.test',
            'locale' => 'nl',
            'role' => User::ROLE_EMPLOYEE,
            'password' => 'wachtwoord123',
            'send_account_email' => false,
        ], $tenant->id);
    }

    public function test_create_colleague_succeeds_below_limit(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        User::factory()->admin()->for($tenant)->create();
        User::factory()->employee()->count(1)->for($tenant)->create();

        $user = app(CreateColleagueAction::class)->handle([
            'name' => 'Tweede',
            'email' => 'tweede@example.test',
            'locale' => 'nl',
            'role' => User::ROLE_EMPLOYEE,
            'password' => 'wachtwoord123',
            'send_account_email' => false,
        ], $tenant->id);

        $this->assertSame('tweede@example.test', $user->email);
        $this->assertSame(3, User::query()->where('tenant_id', $tenant->id)->count());
    }
}

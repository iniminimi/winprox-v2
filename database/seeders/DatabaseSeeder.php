<?php

namespace Database\Seeders;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\IssuePhoto;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create(['name' => 'Demo Facility']);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Beheerder',
            'email' => 'admin@winprox.test',
            'password' => Hash::make('password'),
            'is_superuser' => false,
        ]);

        User::create([
            'tenant_id' => null,
            'name' => 'Platform Superuser',
            'email' => 'super@winprox.test',
            'password' => Hash::make('password'),
            'is_superuser' => true,
        ]);

        Tenancy::actAs($tenant->id);

        $this->seedTenantData($admin);

        Tenancy::forget();
    }

    protected function seedTenantData(User $admin): void
    {
        $locationA = Location::create(['name' => 'Hoofdgebouw', 'address' => 'Stationsstraat 1, Antwerpen']);
        $locationB = Location::create(['name' => 'Magazijn Noord', 'address' => 'Havenlaan 22, Antwerpen']);

        $units = [
            Unit::create(['location_id' => $locationA->id, 'name' => 'Lift A']),
            Unit::create(['location_id' => $locationA->id, 'name' => 'Vergaderzaal 1.04']),
            Unit::create(['location_id' => $locationB->id, 'name' => 'Laadkade 3']),
        ];

        $teamTechniek = InternalTeam::create(['name' => 'Technische dienst']);
        $teamSchoonmaak = InternalTeam::create(['name' => 'Schoonmaak']);
        $teamElektriciteit = InternalTeam::create(['name' => 'Elektriciteit']);

        foreach ([$teamTechniek, $teamSchoonmaak, $teamElektriciteit] as $team) {
            Worker::create(['internal_team_id' => $team->id, 'name' => fake()->name()]);
            Worker::create(['internal_team_id' => $team->id, 'name' => fake()->name()]);
        }

        $createIssue = app(CreateIssueAction::class);
        $approveIssue = app(ApproveIssueAction::class);
        $updateStatus = app(UpdateTaskStatusAction::class);

        // 1. Niet-goedgekeurde QR-melding met foto's (inhoud blijft geblurd).
        $pending = $createIssue->handle([
            'location_id' => $locationA->id,
            'unit_id' => $units[0]->id,
            'reporter_name' => 'Anonieme melder',
            'reporter_contact' => 'melder@example.com',
            'description' => 'De lift blijft steken tussen verdieping 2 en 3 en maakt een hard geluid.',
        ]);
        IssuePhoto::create(['issue_id' => $pending->id, 'path' => 'issue-photos/demo-lift-1.jpg']);
        IssuePhoto::create(['issue_id' => $pending->id, 'path' => 'issue-photos/demo-lift-2.jpg']);

        // 2. Goedgekeurde melding met een nieuwe taak (status: Nieuw).
        $new = $createIssue->handle([
            'location_id' => $locationA->id,
            'unit_id' => $units[1]->id,
            'description' => 'Verwarming in vergaderzaal 1.04 werkt niet.',
        ], [$teamTechniek->id]);
        $approveIssue->handle($new, $admin);

        // 3. Goedgekeurde melding, één taak in uitvoering (status: In uitvoering).
        $inProgress = $createIssue->handle([
            'location_id' => $locationB->id,
            'unit_id' => $units[2]->id,
            'description' => 'Laaddeur 3 sluit niet meer volledig.',
        ], [$teamTechniek->id, $teamElektriciteit->id]);
        $updateStatus->handle($inProgress->tasks()->first(), TaskStatus::InProgress);
        $approveIssue->handle($inProgress, $admin);

        // 4. Goedgekeurde melding, alle taken afgehandeld (status: Afgehandeld).
        $done = $createIssue->handle([
            'location_id' => $locationA->id,
            'description' => 'Koffievlek op de gang bij de receptie opruimen.',
        ], [$teamSchoonmaak->id]);
        foreach ($done->tasks as $task) {
            $updateStatus->handle($task, TaskStatus::Done);
        }
        $approveIssue->handle($done, $admin);

        // 5. Goedgekeurde melding, alle taken gesloten (status: Gesloten).
        $closed = $createIssue->handle([
            'location_id' => $locationB->id,
            'description' => 'Kapotte tl-lamp in het magazijn vervangen.',
        ], [$teamElektriciteit->id]);
        foreach ($closed->tasks as $task) {
            $updateStatus->handle($task, TaskStatus::Closed);
        }
        $approveIssue->handle($closed, $admin);
    }
}

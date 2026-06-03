<?php

namespace Database\Seeders;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\InternalTeam;
use App\Models\Issue;
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
            'role' => User::ROLE_ADMIN,
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

        // Vaste, URL-veilige tokens zodat de publieke QR-URL's stabiel/testbaar zijn.
        $teamTechniek = InternalTeam::create(['name' => 'Technische dienst', 'field_qr_token' => 'team-technische-dienst']);
        $teamSchoonmaak = InternalTeam::create(['name' => 'Schoonmaak', 'field_qr_token' => 'team-schoonmaak']);
        $teamElektriciteit = InternalTeam::create(['name' => 'Elektriciteit', 'field_qr_token' => 'team-elektriciteit']);

        // Eén leeg team om open registratie via team-QR te demonstreren (geen workers).
        InternalTeam::create(['name' => 'Tuinonderhoud (nog op te starten)', 'field_qr_token' => 'team-tuinonderhoud']);

        // Workers met elk een eigen icoon-slug (per team uniek).
        $workers = [
            [$teamTechniek, 'Sven', 'Peeters', 'heart'],
            [$teamTechniek, 'Lena', 'Janssens', 'plane'],
            [$teamSchoonmaak, 'Marie', 'Dubois', 'car'],
            [$teamSchoonmaak, 'Tom', 'Claes', 'star'],
            [$teamElektriciteit, 'Karim', 'El Amrani', 'zap'],
            [$teamElektriciteit, 'Nina', 'Vermeulen', 'gem'],
        ];
        foreach ($workers as [$team, $first, $last, $icon]) {
            Worker::create([
                'internal_team_id' => $team->id,
                'first_name' => $first,
                'last_name' => $last,
                'field_icon_slug' => $icon,
                'is_active' => true,
            ]);
        }

        // Eén worker zonder bevestigd icoon (claimable): mag op team-QR zijn icoon kiezen.
        Worker::create([
            'internal_team_id' => $teamTechniek->id,
            'first_name' => 'Jonas',
            'last_name' => 'Maes',
            'field_icon_slug' => null,
            'is_active' => true,
        ]);

        $units = [
            Unit::create(['location_id' => $locationA->id, 'default_internal_team_id' => $teamTechniek->id, 'name' => 'Lift A']),
            Unit::create(['location_id' => $locationA->id, 'default_internal_team_id' => $teamTechniek->id, 'name' => 'Vergaderzaal 1.04']),
            Unit::create(['location_id' => $locationB->id, 'default_internal_team_id' => $teamElektriciteit->id, 'name' => 'Laadkade 3']),
            Unit::create(['location_id' => $locationB->id, 'default_internal_team_id' => $teamSchoonmaak->id, 'name' => 'Sanitair magazijn']),
        ];

        // Documenten: publiek downloadbaar + één dat verificatie vereist.
        Document::create([
            'location_id' => $locationA->id,
            'title' => 'Handleiding Lift A',
            'description' => 'Bedienings- en veiligheidshandleiding van de lift.',
            'file_path' => 'documents/handleiding-lift-a.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 482_311,
            'is_public' => true,
            'requires_verification' => false,
            'is_active' => true,
            'published_at' => now()->subWeek(),
        ]);
        Document::create([
            'location_id' => $locationA->id,
            'unit_id' => $units[0]->id,
            'title' => 'Onderhoudscontract (vertrouwelijk)',
            'description' => 'Beschikbaar na verificatie door de beheerder.',
            'file_path' => 'documents/onderhoudscontract.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1_204_882,
            'is_public' => true,
            'requires_verification' => true,
            'is_active' => true,
            'published_at' => now()->subDays(3),
        ]);

        // Mededelingen: één actieve, één verlopen (mag niet meer verschijnen).
        Announcement::create([
            'location_id' => $locationA->id,
            'title' => 'Gepland groot onderhoud',
            'body' => 'Volgende week dinsdag voeren we groot onderhoud uit aan Lift A tussen 8u en 12u.',
            'is_active' => true,
            'published_at' => now()->subDay(),
            'expires_at' => now()->addWeek(),
        ]);
        Announcement::create([
            'location_id' => $locationA->id,
            'title' => 'Oude mededeling (verlopen)',
            'body' => 'Deze mededeling is verlopen en hoort niet meer zichtbaar te zijn.',
            'is_active' => true,
            'published_at' => now()->subWeeks(3),
            'expires_at' => now()->subWeek(),
        ]);

        $createIssue = app(CreateIssueAction::class);
        $approveIssue = app(ApproveIssueAction::class);
        $updateStatus = app(UpdateTaskStatusAction::class);

        // 1. Niet-goedgekeurde QR-melding met foto's (inhoud blijft publiek geblurd).
        $pending = $createIssue->handle([
            'location_id' => $locationA->id,
            'unit_id' => $units[0]->id,
            'reporter_name' => 'Anonieme melder',
            'description' => 'De lift blijft steken tussen verdieping 2 en 3 en maakt een hard geluid.',
        ], [$teamTechniek->id]);
        IssuePhoto::create(['issue_id' => $pending->id, 'path' => 'issue-photos/demo-lift-1.jpg']);
        IssuePhoto::create(['issue_id' => $pending->id, 'path' => 'issue-photos/demo-lift-2.jpg']);

        // 2. Tweede niet-goedgekeurde QR-melding (blur op tweede unit).
        $pending2 = $createIssue->handle([
            'location_id' => $locationB->id,
            'unit_id' => $units[2]->id,
            'description' => 'Laaddeur 3 sluit niet meer volledig, tocht en regen binnen.',
        ], [$teamElektriciteit->id]);
        IssuePhoto::create(['issue_id' => $pending2->id, 'path' => 'issue-photos/demo-laadkade-1.jpg']);

        // 3. Goedgekeurde melding met een nieuwe taak (status: Nieuw).
        $new = $createIssue->handle([
            'location_id' => $locationA->id,
            'unit_id' => $units[1]->id,
            'description' => 'Verwarming in vergaderzaal 1.04 werkt niet.',
        ], [$teamTechniek->id]);
        $approveIssue->handle($new, $admin);

        // 4. Goedgekeurde melding, één taak in uitvoering (status: In uitvoering).
        $inProgress = $createIssue->handle([
            'location_id' => $locationB->id,
            'unit_id' => $units[3]->id,
            'description' => 'Kraan in sanitair magazijn blijft lopen.',
        ], [$teamSchoonmaak->id]);
        $updateStatus->handle($inProgress->tasks()->first(), TaskStatus::InProgress);
        $approveIssue->handle($inProgress, $admin);

        // 5. Goedgekeurde melding, alle taken gesloten (status: Gesloten).
        $closed = $createIssue->handle([
            'location_id' => $locationB->id,
            'description' => 'Kapotte tl-lamp in het magazijn vervangen.',
        ], [$teamElektriciteit->id]);
        foreach ($closed->tasks as $task) {
            $updateStatus->handle($task, TaskStatus::Closed, $admin, 'Demo: taak gesloten zonder uitvoering.');
        }
        $approveIssue->handle($closed, $admin);

        // Demo-beheerder: naam "Beheerder" is geen rol — oude seeds hadden soms employee.
        User::query()
            ->where('email', 'admin@winprox.test')
            ->where('is_superuser', false)
            ->update(['role' => User::ROLE_ADMIN]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HospitalDemoSeeder extends Seeder
{
    public function run(int $tenantId, ?int $actorUserId = null): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            throw new RuntimeException("Tenant {$tenantId} introuvable.");
        }

        $admin = $this->resolveAdmin($tenantId, $actorUserId);

        Tenancy::actAs($tenantId);

        try {
            $tenant->update(['name' => 'Hôpital Saint-Raphaël']);

            $teams = $this->seedTeams();
            $this->seedWorkers($teams);
            $locations = $this->seedLocations();
            $categories = $this->seedCategories($tenantId, $teams);
            $units = $this->seedUnits($locations, $categories);
            $this->seedIssuesAndTasks($admin, $teams, $locations, $units);
        } finally {
            Tenancy::forget();
        }
    }

    public function purge(int $tenantId): void
    {
        Tenancy::actAs($tenantId);

        try {
            DB::transaction(function () use ($tenantId): void {
                Task::query()->where('tenant_id', $tenantId)->delete();
                Issue::query()->where('tenant_id', $tenantId)->delete();
                Unit::query()->where('tenant_id', $tenantId)->delete();
                Category::query()->where('tenant_id', $tenantId)->delete();
                Worker::query()->where('tenant_id', $tenantId)->delete();
                InternalTeam::query()->where('tenant_id', $tenantId)->delete();
                Location::query()->where('tenant_id', $tenantId)->delete();
            });
        } finally {
            Tenancy::forget();
        }
    }

    public function hasExistingFacilityData(int $tenantId): bool
    {
        return InternalTeam::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    private function resolveAdmin(int $tenantId, ?int $actorUserId): User
    {
        if ($actorUserId !== null) {
            $admin = User::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($actorUserId)
                ->first();
            if ($admin !== null) {
                return $admin;
            }
        }

        $admin = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('id')
            ->first();

        if ($admin === null) {
            throw new RuntimeException("Aucun administrateur trouvé pour le tenant {$tenantId}.");
        }

        return $admin;
    }

    /**
     * @return array<string, InternalTeam>
     */
    private function seedTeams(): array
    {
        return [
            'maintenance' => InternalTeam::create([
                'name' => 'Maintenance technique',
                'field_qr_token' => 'chu-maintenance',
            ]),
            'housekeeping' => InternalTeam::create([
                'name' => 'Blanchisserie et hygiène',
                'field_qr_token' => 'chu-blanchisserie',
            ]),
            'security' => InternalTeam::create([
                'name' => 'Sécurité',
                'field_qr_token' => 'chu-securite',
            ]),
            'it' => InternalTeam::create([
                'name' => 'Informatique',
                'field_qr_token' => 'chu-informatique',
            ]),
        ];
    }

    /**
     * @param  array<string, InternalTeam>  $teams
     */
    private function seedWorkers(array $teams): void
    {
        $workers = [
            [$teams['maintenance'], 'Marc', 'Dubois', 'heart', true],
            [$teams['maintenance'], 'Sophie', 'Laurent', 'plane', false],
            [$teams['maintenance'], 'Thomas', 'Bernard', 'crown', false],
            [$teams['housekeeping'], 'Claire', 'Martin', 'car', true],
            [$teams['housekeeping'], 'Émilie', 'Petit', 'leaf', false],
            [$teams['security'], 'Philippe', 'Moreau', 'star', true],
            [$teams['security'], 'Nathalie', 'Simon', 'anchor', false],
            [$teams['it'], 'Julien', 'Lefèvre', 'zap', true],
            [$teams['it'], 'Camille', 'Rousseau', 'gem', false],
            [$teams['it'], 'Antoine', 'Girard', 'key', false],
        ];

        foreach ($workers as [$team, $first, $last, $icon, $isTeamleader]) {
            Worker::create([
                'internal_team_id' => $team->id,
                'first_name' => $first,
                'last_name' => $last,
                'field_icon_slug' => $icon,
                'is_active' => true,
                'is_teamleader' => $isTeamleader,
            ]);
        }
    }

    /**
     * @return list<Location>
     */
    private function seedLocations(): array
    {
        return [
            Location::create([
                'name' => 'Bâtiment principal',
                'street' => 'Avenue de la Santé',
                'house_number' => '45',
                'postal_code' => '1200',
                'city' => 'Bruxelles',
                'country_code' => 'BE',
                'original_language' => 'fr',
            ]),
            Location::create([
                'name' => 'Urgences',
                'street' => 'Avenue de la Santé',
                'house_number' => '45',
                'postal_code' => '1200',
                'city' => 'Bruxelles',
                'country_code' => 'BE',
                'original_language' => 'fr',
            ]),
            Location::create([
                'name' => 'Bloc opératoire',
                'street' => 'Avenue de la Santé',
                'house_number' => '47',
                'postal_code' => '1200',
                'city' => 'Bruxelles',
                'country_code' => 'BE',
                'original_language' => 'fr',
            ]),
            Location::create([
                'name' => 'Pharmacie centrale',
                'street' => 'Rue du Remède',
                'house_number' => '8',
                'postal_code' => '1200',
                'city' => 'Bruxelles',
                'country_code' => 'BE',
                'original_language' => 'fr',
            ]),
            Location::create([
                'name' => 'Parking visiteurs',
                'street' => 'Boulevard des Visiteurs',
                'house_number' => '2',
                'postal_code' => '1200',
                'city' => 'Bruxelles',
                'country_code' => 'BE',
                'original_language' => 'fr',
            ]),
        ];
    }

    /**
     * @param  array<string, InternalTeam>  $teams
     * @return array<string, Category>
     */
    private function seedCategories(int $tenantId, array $teams): array
    {
        $categories = [
            'building' => Category::create(['tenant_id' => $tenantId, 'name' => 'Ascenseurs et bâtiment']),
            'rooms' => Category::create(['tenant_id' => $tenantId, 'name' => 'Chambres et salles']),
            'access' => Category::create(['tenant_id' => $tenantId, 'name' => 'Sécurité et accès']),
            'network' => Category::create(['tenant_id' => $tenantId, 'name' => 'Réseau et informatique']),
        ];

        $categories['building']->teams()->sync([$teams['maintenance']->id]);
        $categories['rooms']->teams()->sync([$teams['housekeeping']->id]);
        $categories['access']->teams()->sync([$teams['security']->id]);
        $categories['network']->teams()->sync([$teams['it']->id]);

        return $categories;
    }

    /**
     * @param  list<Location>  $locations
     * @param  array<string, Category>  $categories
     * @return list<Unit>
     */
    private function seedUnits(array $locations, array $categories): array
    {
        $definitions = [
            [$locations[0], $categories['building'], 'Ascenseur A'],
            [$locations[0], $categories['rooms'], 'Hall d\'accueil'],
            [$locations[0], $categories['rooms'], 'Chambre 204'],
            [$locations[0], $categories['network'], 'Bureau des admissions'],
            [$locations[1], $categories['rooms'], 'Salle d\'attente urgences'],
            [$locations[1], $categories['rooms'], 'Box de triage 3'],
            [$locations[1], $categories['building'], 'Chambre d\'observation 12'],
            [$locations[1], $categories['access'], 'Accès ambulances'],
            [$locations[2], $categories['building'], 'Salle opératoire 4'],
            [$locations[2], $categories['rooms'], 'Salle de réveil'],
            [$locations[2], $categories['building'], 'Stérilisation'],
            [$locations[2], $categories['rooms'], 'Vestiaire personnel'],
            [$locations[3], $categories['rooms'], 'Comptoir pharmacie'],
            [$locations[3], $categories['building'], 'Stock médicaments'],
            [$locations[3], $categories['building'], 'Réserve réfrigérée'],
            [$locations[3], $categories['network'], 'Bureau pharmacien'],
            [$locations[4], $categories['access'], 'Entrée parking P1'],
            [$locations[4], $categories['access'], 'Places handicapés'],
            [$locations[4], $categories['network'], 'Borne de paiement'],
            [$locations[4], $categories['building'], 'Ascenseur parking'],
        ];

        $units = [];
        foreach ($definitions as [$location, $category, $name]) {
            $units[] = Unit::create([
                'location_id' => $location->id,
                'category_id' => $category->id,
                'name' => $name,
                'original_language' => 'fr',
            ]);
        }

        return $units;
    }

    /**
     * @param  array<string, InternalTeam>  $teams
     * @param  list<Location>  $locations
     * @param  list<Unit>  $units
     */
    private function seedIssuesAndTasks(User $admin, array $teams, array $locations, array $units): void
    {
        $createIssue = app(CreateIssueAction::class);
        $approveIssue = app(ApproveIssueAction::class);
        $updateStatus = app(UpdateTaskStatusAction::class);

        $scenarios = [
            ['qr', false, TaskStatus::New, null, $units[0], $teams['maintenance'], 'L\'ascenseur A reste bloqué entre le 2e et le 3e étage et émet un bruit métallique.', TaskPriority::Prio1],
            ['qr', false, TaskStatus::New, null, $units[2], $teams['housekeeping'], 'Fuite d\'eau au plafond de la chambre 204, flaques sur le sol.', TaskPriority::Prio1],
            ['qr', false, TaskStatus::New, null, $units[7], $teams['security'], 'La porte d\'accès ambulances ne se referme plus complètement.', TaskPriority::Prio2],
            ['qr', false, TaskStatus::New, null, $units[18], $teams['it'], 'La borne de paiement du parking n\'accepte plus les cartes bancaires.', TaskPriority::Prio3],
            ['manager', true, TaskStatus::New, null, $units[4], $teams['housekeeping'], 'Sol glissant signalé dans la salle d\'attente des urgences.', TaskPriority::Prio2],
            ['manager', true, TaskStatus::New, null, $units[8], $teams['maintenance'], 'Climatisation en panne dans la salle opératoire 4.', TaskPriority::Prio1],
            ['manager', true, TaskStatus::New, null, $units[15], $teams['it'], 'Poste informatique du bureau pharmacien ne démarre plus.', TaskPriority::Prio2],
            ['manager', true, TaskStatus::New, null, $units[1], $teams['housekeeping'], 'Poubelles pleines et non évacuées dans le hall d\'accueil.', TaskPriority::Prio4],
            ['manager', true, TaskStatus::InProgress, null, $units[5], $teams['housekeeping'], 'Éclairage défectueux dans le box de triage 3.', TaskPriority::Prio3],
            ['manager', true, TaskStatus::InProgress, null, $units[9], $teams['maintenance'], 'Table opératoire de la salle 4 ne se verrouille plus correctement.', TaskPriority::Prio1],
            ['manager', true, TaskStatus::InProgress, null, $units[12], $teams['housekeeping'], 'Linge sale accumulé près du comptoir pharmacie.', TaskPriority::Prio3],
            ['manager', true, TaskStatus::InProgress, null, $units[16], $teams['security'], 'Barrière du parking P1 reste ouverte sans autorisation.', TaskPriority::Prio2],
            ['manager', true, TaskStatus::Done, null, $units[10], $teams['housekeeping'], 'Sol de la salle de réveil nettoyé après renversement de perfusion.', TaskPriority::Prio3],
            ['manager', true, TaskStatus::Done, null, $units[13], $teams['maintenance'], 'Étagère instable sécurisée dans le stock médicaments.', TaskPriority::Prio4],
            ['manager', true, TaskStatus::Done, null, $units[3], $teams['it'], 'Imprimante du bureau des admissions rétablie après coupure réseau.', TaskPriority::Prio3],
            ['manager', true, TaskStatus::Done, null, $units[11], $teams['maintenance'], 'Autoclave de stérilisation remis en service après maintenance.', TaskPriority::Prio2],
            ['manager', true, TaskStatus::Closed, 'Doublon signalé par un visiteur.', $units[6], $teams['maintenance'], 'Chambre d\'observation 12 : signalement erroné, aucun problème constaté.', TaskPriority::Prio4],
            ['manager', true, TaskStatus::Closed, 'Problème résolu par l\'équipe de garde avant intervention.', $units[14], $teams['maintenance'], 'Alarme température de la réserve réfrigérée : faux positif.', TaskPriority::Prio3],
            ['manager', true, TaskStatus::Closed, 'Hors périmètre WinProx — signalé au prestataire externe.', $units[17], $teams['security'], 'Place handicapés occupée par un véhicule sans badge.', TaskPriority::Prio4],
            ['manager', true, TaskStatus::Closed, 'Signalement annulé par le service soignant.', $units[19], $teams['maintenance'], 'Ascenseur parking : rumeur infondée, appareil fonctionnel.', TaskPriority::Prio4],
        ];

        foreach ($scenarios as $index => [$source, $approve, $targetStatus, $closeReason, $unit, $team, $description, $priority]) {
            $issue = $createIssue->handle([
                'location_id' => $unit->location_id,
                'unit_id' => $unit->id,
                'description' => $description,
                'original_language' => 'fr',
                'source' => $source,
                'reporter_name' => $source === 'qr' ? 'Visiteur anonyme' : null,
            ], $source === 'qr' ? [$team->id] : []);

            if ($approve) {
                $approveIssue->handle($issue, $admin);
                $task = $issue->tasks()->first();
                if ($task === null) {
                    $task = $issue->tasks()->create([
                        'internal_team_id' => $team->id,
                        'status' => TaskStatus::New,
                        'priority' => $priority,
                        'original_language' => 'fr',
                    ]);
                } else {
                    $task->update(['priority' => $priority]);
                }

                $this->applyTaskStatus($updateStatus, $task, $targetStatus, $admin, $closeReason);
            }
        }
    }

    private function applyTaskStatus(
        UpdateTaskStatusAction $updateStatus,
        Task $task,
        TaskStatus $targetStatus,
        User $admin,
        ?string $closeReason,
    ): void {
        if ($targetStatus === TaskStatus::New) {
            return;
        }

        if ($targetStatus === TaskStatus::InProgress) {
            $updateStatus->handle($task->fresh(), TaskStatus::InProgress);

            return;
        }

        if ($targetStatus === TaskStatus::Done) {
            $updateStatus->handle($task->fresh(), TaskStatus::InProgress);
            $updateStatus->handle($task->fresh(), TaskStatus::Done);

            return;
        }

        if ($targetStatus === TaskStatus::Closed) {
            $updateStatus->handle(
                $task->fresh(),
                TaskStatus::Closed,
                $admin,
                $closeReason ?? 'Clôture démo.',
            );
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Worker;
use Database\Seeders\HospitalDemoSeeder;
use Illuminate\Console\Command;

class SeedHospitalDemoCommand extends Command
{
    protected $signature = 'winprox:seed-hospital-demo
                            {tenant=6 : ID du tenant à remplir}
                            {--user= : ID ou e-mail de l\'administrateur (approbation des signalements)}
                            {--force : Supprimer les équipes, lieux et signalements existants du tenant}';

    protected $description = 'Remplit un tenant avec des données de démo hospitalière en français (équipes, workers, lieux, units, signalements, tâches).';

    public function handle(HospitalDemoSeeder $seeder): int
    {
        $tenantId = (int) $this->argument('tenant');
        $actorUserId = $this->resolveActorUserId($tenantId);

        if ($seeder->hasExistingFacilityData($tenantId) && ! $this->option('force')) {
            $this->error("Le tenant {$tenantId} contient déjà des données facility. Utilisez --force pour les remplacer.");

            return self::FAILURE;
        }

        if ($this->option('force') && $seeder->hasExistingFacilityData($tenantId)) {
            $this->warn("Suppression des données facility existantes pour le tenant {$tenantId}…");
            $seeder->purge($tenantId);
        }

        $this->info("Génération des données de démo hospitalière pour le tenant {$tenantId}…");
        $seeder->run($tenantId, $actorUserId);

        $this->table(
            ['Type', 'Nombre'],
            [
                ['Équipes', InternalTeam::withoutGlobalScopes()->where('tenant_id', $tenantId)->count()],
                ['Workers', Worker::withoutGlobalScopes()->where('tenant_id', $tenantId)->count()],
                ['Lieux', Location::withoutGlobalScopes()->where('tenant_id', $tenantId)->count()],
                ['Units', Unit::withoutGlobalScopes()->where('tenant_id', $tenantId)->count()],
                ['Signalements', Issue::withoutGlobalScopes()->where('tenant_id', $tenantId)->count()],
                ['Tâches', Task::withoutGlobalScopes()->where('tenant_id', $tenantId)->count()],
            ],
        );

        $this->info('Terminé. Connectez-vous au tenant pour enregistrer la vidéo de démo.');

        return self::SUCCESS;
    }

    private function resolveActorUserId(int $tenantId): ?int
    {
        $userOption = $this->option('user');
        if ($userOption === null || $userOption === '') {
            return null;
        }

        $user = is_numeric($userOption)
            ? \App\Models\User::query()->where('tenant_id', $tenantId)->whereKey((int) $userOption)->first()
            : \App\Models\User::query()->where('tenant_id', $tenantId)->where('email', $userOption)->first();

        if ($user === null) {
            $this->error("Utilisateur introuvable pour le tenant {$tenantId}: {$userOption}");

            exit(self::FAILURE);
        }

        return (int) $user->id;
    }
}

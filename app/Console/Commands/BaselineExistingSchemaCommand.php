<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Deploy\BaselineExistingSchemaMigrationsAction;
use Illuminate\Console\Command;

class BaselineExistingSchemaCommand extends Command
{
    protected $signature = 'winprox:baseline-existing-schema';

    protected $description = 'Markeer bestaande migraties als uitgevoerd als de tabellen al bestaan';

    public function handle(BaselineExistingSchemaMigrationsAction $baseline): int
    {
        $count = $baseline->handle();

        if ($count > 0) {
            $this->info("Bestaand schema: {$count} migraties als uitgevoerd gemarkeerd. Geen tabellen aangemaakt.");
        }

        return self::SUCCESS;
    }
}

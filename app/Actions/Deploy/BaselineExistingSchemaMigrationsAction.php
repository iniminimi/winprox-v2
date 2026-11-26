<?php

declare(strict_types=1);

namespace App\Actions\Deploy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Throwable;

/**
 * Als het schema al bestaat maar de migratielijst leeg is, markeer de
 * bestaande migraties als uitgevoerd. Er worden geen tabellen aangemaakt.
 */
class BaselineExistingSchemaMigrationsAction
{
    public function handle(): int
    {
        try {
            if (! Schema::hasTable('users')) {
                return 0;
            }

            if (! Schema::hasTable('migrations')) {
                Artisan::call('migrate:install');
            }

            if (DB::table('migrations')->exists()) {
                return 0;
            }

            $rows = array_map(
                fn (string $name): array => ['migration' => $name, 'batch' => 1],
                $this->migrationNames(),
            );

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('migrations')->insert($chunk);
            }

            return count($rows);
        } catch (PDOException) {
            return 0;
        } catch (Throwable $e) {
            if ($this->isConnectionFailure($e)) {
                return 0;
            }

            throw $e;
        }
    }

    /**
     * @return list<string>
     */
    private function migrationNames(): array
    {
        $names = array_map(
            fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            glob(database_path('migrations/*.php')) ?: [],
        );
        sort($names);

        return array_values($names);
    }

    private function isConnectionFailure(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'SQLSTATE[HY000]')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'getaddrinfo');
    }
}

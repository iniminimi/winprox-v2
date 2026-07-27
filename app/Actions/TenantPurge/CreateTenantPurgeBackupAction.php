<?php

namespace App\Actions\TenantPurge;

use App\Models\Tenant;
use App\Models\TenantPurgeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Schrijft een gzipped SQL-snapshot van tenant-rijen (zonder mediabestanden).
 */
final class CreateTenantPurgeBackupAction
{
    /** Tabellen met tenant_id die we overslaan (media-metadata mag mee; bestanden niet). */
    private const SKIP_TABLES = [
        // geen — rows mogen mee; alleen disk files worden niet gekopieerd
    ];

    public function handle(Tenant $tenant, TenantPurgeRequest $request): string
    {
        $disk = Storage::disk('local');
        $dir = trim((string) config('tenant_purge.backup_directory', 'tenant-purge-backups'), '/');
        $relative = $dir.'/tenant-'.$tenant->id.'-purge-'.$request->id.'-'.now()->format('YmdHis').'.sql.gz';

        $disk->makeDirectory($dir);

        $absolute = $disk->path($relative);
        $gz = gzopen($absolute, 'wb9');
        if ($gz === false) {
            throw new \RuntimeException('Could not open purge backup for writing.');
        }

        try {
            $this->writeLine($gz, '-- WinProx tenant purge snapshot (no media files)');
            $this->writeLine($gz, '-- tenant_id='.$tenant->id.' name='.str_replace(["\n", "\r"], '', $tenant->name));
            $this->writeLine($gz, '-- purge_request_id='.$request->id);
            $this->writeLine($gz, '-- created_at='.now()->toIso8601String());
            $this->writeLine($gz, '');

            $this->dumpTable($gz, 'tenants', 'id', (int) $tenant->id);

            $tables = $this->tenantScopedTables();
            foreach ($tables as $table) {
                if (in_array($table, self::SKIP_TABLES, true)) {
                    continue;
                }
                $this->dumpTable($gz, $table, 'tenant_id', (int) $tenant->id);
            }
        } finally {
            gzclose($gz);
        }

        return $relative;
    }

    /**
     * @return list<string>
     */
    private function tenantScopedTables(): array
    {
        $tables = [];
        foreach (Schema::getTableListing() as $table) {
            if ($table === 'tenants' || $table === 'tenant_purge_requests') {
                continue;
            }
            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            $tables[] = $table;
        }
        sort($tables);

        return $tables;
    }

    /**
     * @param  resource  $gz
     */
    private function dumpTable($gz, string $table, string $column, int $id): void
    {
        $query = DB::table($table)->where($column, $id);
        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        }
        $rows = $query->get();
        if ($rows->isEmpty()) {
            return;
        }

        $this->writeLine($gz, '-- table: '.$table.' ('.$rows->count().' rows)');

        foreach ($rows as $row) {
            $data = (array) $row;
            $columns = array_map(fn ($c) => '`'.str_replace('`', '``', $c).'`', array_keys($data));
            $values = array_map(fn ($v) => $this->sqlValue($v), array_values($data));
            $this->writeLine(
                $gz,
                'INSERT INTO `'.$table.'` ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).');'
            );
        }

        $this->writeLine($gz, '');
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;

        return "'".str_replace(["\\", "'"], ["\\\\", "\\'"], $string)."'";
    }

    /**
     * @param  resource  $gz
     */
    private function writeLine($gz, string $line): void
    {
        gzwrite($gz, $line."\n");
    }
}

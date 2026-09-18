<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const RENAME = [
        'Inklokken' => 'Aanmelden',
        'Clock in' => 'Sign in',
        'Einstempeln' => 'Anmeldung',
        'Pointage' => 'Connexion',
        'Fichar' => 'Inicio de sesión',
        'Timbratura' => 'Accesso',
    ];

    public function up(): void
    {
        $this->rename(self::RENAME);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAME));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rename(array $map): void
    {
        foreach ($map as $from => $to) {
            $rows = DB::table('clock_points')->where('name', $from)->get(['id', 'tenant_id']);
            foreach ($rows as $row) {
                $taken = DB::table('clock_points')
                    ->where('tenant_id', $row->tenant_id)
                    ->where('name', $to)
                    ->where('id', '!=', $row->id)
                    ->exists();
                if ($taken) {
                    continue;
                }

                DB::table('clock_points')->where('id', $row->id)->update([
                    'name' => $to,
                    'updated_at' => now(),
                ]);
            }
        }
    }
};

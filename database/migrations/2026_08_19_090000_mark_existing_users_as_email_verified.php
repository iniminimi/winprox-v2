<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * E-mailverificatie wordt vanaf nu vereist. Bestaande accounts zijn vóór deze regel
     * aangemaakt en mogen niet buitengesloten worden: markeer ze als geverifieerd op hun
     * aanmaakmoment.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Niet omkeerbaar: we weten achteraf niet welke accounts hierdoor geverifieerd zijn.
    }
};

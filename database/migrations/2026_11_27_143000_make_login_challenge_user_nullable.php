<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backoffice_login_challenges')) {
            return;
        }

        $user = collect(Schema::getColumns('backoffice_login_challenges'))->firstWhere('name', 'user_id');
        if ($user === null || ($user['nullable'] ?? false)) {
            return;
        }

        Schema::table('backoffice_login_challenges', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('backoffice_login_challenges', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        Schema::table('backoffice_login_challenges', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Het ticket mag zonder gebruiker bestaan tot de telefoon het wachtwoord geeft.
    }
};

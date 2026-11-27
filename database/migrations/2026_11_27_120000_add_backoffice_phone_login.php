<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'backoffice_phone_login_enabled')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->boolean('backoffice_phone_login_enabled')->default(false)->after('translations_enabled');
            });
        }

        if (! Schema::hasColumn('tenants', 'backoffice_phone_login_decided_at')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->timestamp('backoffice_phone_login_decided_at')->nullable()->after('backoffice_phone_login_enabled');
            });
        }

        if (! Schema::hasColumn('tenants', 'login_alert_enabled')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->boolean('login_alert_enabled')->default(false)->after('backoffice_phone_login_decided_at');
            });
        }

        if (! Schema::hasColumn('tenants', 'login_alert_start')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->char('login_alert_start', 5)->default('22:00')->after('login_alert_enabled');
            });
        }

        if (! Schema::hasColumn('tenants', 'login_alert_end')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->char('login_alert_end', 5)->default('06:00')->after('login_alert_start');
            });
        }

        if (! Schema::hasColumn('users', 'phone_reregistration_open')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('phone_reregistration_open')->default(false)->after('role');
            });
        }

        if (! Schema::hasColumn('users', 'phone_revoked_label')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone_revoked_label')->nullable()->after('phone_reregistration_open');
            });
        }

        if (! Schema::hasTable('user_passkey_credentials')) {
            $this->createUserPasskeyCredentials();
        }

        if (! Schema::hasTable('backoffice_login_challenges')) {
            $this->createBackofficeLoginChallenges();
        }

        $this->comment('user_passkey_credentials', 'Passkey van een beheerder of medewerker.');
        $this->comment('backoffice_login_challenges', 'Eenmalige QR-aanmelding voor de computer.');
    }

    private function createUserPasskeyCredentials(): void
    {
        Schema::create('user_passkey_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id')->unique();
            $table->text('credential_public_key');
            $table->string('user_handle');
            $table->json('transports')->nullable();
            $table->string('aaguid')->nullable();
            $table->boolean('backup_eligible')->default(false);
            $table->boolean('backup_status')->default(false);
            $table->unsignedInteger('sign_count')->default(0);
            $table->string('device_label');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    private function createBackofficeLoginChallenges(): void
    {
        Schema::create('backoffice_login_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->char('confirmation_code', 3);
            $table->string('session_id');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('computer_label');
            $table->string('status', 32);
            $table->timestamp('expires_at');
            $table->text('assertion_options')->nullable();
            $table->text('registration_options')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backoffice_login_challenges');
        Schema::dropIfExists('user_passkey_credentials');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_reregistration_open', 'phone_revoked_label']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'backoffice_phone_login_enabled',
                'backoffice_phone_login_decided_at',
                'login_alert_enabled',
                'login_alert_start',
                'login_alert_end',
            ]);
        });
    }

    private function comment(string $table, string $comment): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable($table)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` COMMENT %s',
            $table,
            DB::getPdo()->quote($comment),
        ));
    }
};

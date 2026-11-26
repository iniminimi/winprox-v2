<?php

use App\Actions\Deploy\BaselineExistingSchemaMigrationsAction;
use Illuminate\Support\Facades\DB;

it('doet niets als de migratiehistorie al gevuld is', function () {
    $before = DB::table('migrations')->count();

    expect($before)->toBeGreaterThan(0)
        ->and(app(BaselineExistingSchemaMigrationsAction::class)->handle())->toBe(0)
        ->and(DB::table('migrations')->count())->toBe($before);
});

it('vult een lege migratiehistorie als de tabel users al bestaat', function () {
    DB::table('migrations')->delete();

    $inserted = app(BaselineExistingSchemaMigrationsAction::class)->handle();

    expect($inserted)->toBeGreaterThan(200)
        ->and(DB::table('migrations')->count())->toBe($inserted)
        ->and(DB::table('migrations')->where('migration', '0001_01_01_000000_create_users_table')->exists())->toBeTrue()
        ->and(app(BaselineExistingSchemaMigrationsAction::class)->handle())->toBe(0);
});

<?php

use App\Support\Time\BelgianPublicHolidayCalendar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('belgian_public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedSmallInteger('year');
            $table->string('code', 32);
            $table->unique(['year', 'code']);
            $table->index('year');
        });

        $rows = [];
        for ($year = BelgianPublicHolidayCalendar::FIRST_YEAR; $year <= BelgianPublicHolidayCalendar::LAST_YEAR; $year++) {
            foreach (BelgianPublicHolidayCalendar::datesForYear($year) as $code => $date) {
                $rows[] = [
                    'date' => $date,
                    'year' => $year,
                    'code' => $code,
                ];
            }
        }
        DB::table('belgian_public_holidays')->insert($rows);

        Schema::create('worker_weekday_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->unsignedSmallInteger('minutes');
            $table->timestamps();

            $table->unique(['worker_id', 'weekday']);
        });

        Schema::create('worker_holiday_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('effect', 16);
            $table->date('replacement_date')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'holiday_date']);
            $table->index(['worker_id', 'replacement_date']);
            $table->foreign('holiday_date')->references('date')->on('belgian_public_holidays');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_holiday_agreements');
        Schema::dropIfExists('worker_weekday_minutes');
        Schema::dropIfExists('belgian_public_holidays');
    }
};

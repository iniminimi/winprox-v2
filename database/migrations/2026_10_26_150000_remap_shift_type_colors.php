<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemapShiftTypeColors extends Migration
{
    public function up(): void
    {
        $this->apply([
            'emerald' => 'green',
            'mint' => 'green',
            'teal' => 'petrol',
            'sky' => 'blue',
            'ice' => 'blue',
            'navy' => 'blue',
            'amber' => 'orange',
            'gold' => 'yellow',
            'sand' => 'yellow',
            'rose' => 'pink',
            'slate' => 'gray',
        ]);
    }

    public function down(): void
    {
        $this->apply([
            'green' => 'emerald',
            'petrol' => 'teal',
            'blue' => 'sky',
            'purple' => 'sky',
            'pink' => 'rose',
            'orange' => 'amber',
            'yellow' => 'gold',
            'gray' => 'slate',
        ]);
    }

    /**
     * @param  array<string, string>  $map
     */
    private function apply(array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table('shift_types')->where('color', $from)->update(['color' => $to]);
        }
    }
}

return new RemapShiftTypeColors;

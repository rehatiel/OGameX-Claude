<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_missions', function (Blueprint $table) {
            // Stores the actual physical arrival time of the fleet, separate from time_arrival.
            // For most missions: time_physical_arrival == time_arrival.
            // For ACS Defend outbound (mission_type=5, parent_id=NULL): time_arrival includes the hold time
            // (time_arrival = physical_arrival + time_holding), so time_physical_arrival stores the real
            // moment the fleet arrives at the destination before the hold period starts.
            $table->integer('time_physical_arrival')->nullable()->after('time_arrival');
        });

        // Backfill existing records.
        // ACS Defend outbound: physical arrival = time_arrival - time_holding
        DB::statement('
            UPDATE fleet_missions
            SET time_physical_arrival = time_arrival - COALESCE(time_holding, 0)
            WHERE mission_type = 5 AND parent_id IS NULL AND time_holding IS NOT NULL
        ');

        // All other missions: physical arrival == time_arrival
        DB::statement('
            UPDATE fleet_missions
            SET time_physical_arrival = time_arrival
            WHERE time_physical_arrival IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('fleet_missions', function (Blueprint $table) {
            $table->dropColumn('time_physical_arrival');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'officer_cost', 'value' => '8000'],       // DM cost per individual officer per period
            ['key' => 'officer_duration_days', 'value' => '30'], // Duration of officer activation in days
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore($setting);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['officer_cost', 'officer_duration_days'])->delete();
    }
};

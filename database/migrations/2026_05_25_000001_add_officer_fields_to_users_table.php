<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('officer_commander')->default(0)->after('dark_matter');
            $table->unsignedBigInteger('officer_admiral')->default(0)->after('officer_commander');
            $table->unsignedBigInteger('officer_engineer')->default(0)->after('officer_admiral');
            $table->unsignedBigInteger('officer_geologist')->default(0)->after('officer_engineer');
            $table->unsignedBigInteger('officer_technocrat')->default(0)->after('officer_geologist');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'officer_commander',
                'officer_admiral',
                'officer_engineer',
                'officer_geologist',
                'officer_technocrat',
            ]);
        });
    }
};

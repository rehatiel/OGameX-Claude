<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('reward_key', 20);
            $table->timestamp('claimed_at');

            $table->unique(['user_id', 'reward_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rewards');
    }
};

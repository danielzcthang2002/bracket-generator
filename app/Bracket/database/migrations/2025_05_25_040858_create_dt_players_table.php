<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dt_players', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->foreignId('tournament_id')->constrained('dt_tournaments')->cascadeOnDelete();
            $table->integer('seed')->nullable();
            $table->boolean('checked_in')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->integer('final_rank')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dt_players', function(Blueprint $table){
            $table->dropForeign(['tournament_id']);
        });
        Schema::dropIfExists('dt_players');
    }
};

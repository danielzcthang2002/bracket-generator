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
        Schema::create('tournament_match_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_match_id')->constrained('tournament_matches')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();

            // Display/seed ordering within the match (replaces player1/player2 ordering)
            $table->unsignedInteger('position')->nullable();

            $table->decimal('score', 8, 2)->nullable();
            $table->unsignedInteger('rank')->nullable(); // 1st, 2nd, 3rd... within this match
            $table->boolean('is_winner')->default(false);

            $table->timestamps();

            $table->unique(['tournament_match_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_match_participants', function (Blueprint $table) {
            $table->dropForeign(['tournament_match_id']);
            $table->dropForeign(['player_id']);
            $table->dropForeign(['prereq_match_id']);
        });
        Schema::dropIfExists('tournament_match_participants');
    }
};

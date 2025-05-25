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
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->enum('state', ['pending', 'open', 'close', 'complete'])->default('pending');

            $table->foreignId('player1_id')->nullable()->constrained('players', 'id')->nullOnDelete();
            $table->foreignId('player2_id')->nullable()->constrained('players', 'id')->nullOnDelete();

            $table->foreignId('player1_prereq_match_id')->nullable()->constrained('tournament_matches', 'id')->nullOnDelete();
            $table->foreignId('player2_prereq_match_id')->nullable()->constrained('tournament_matches', 'id')->nullOnDelete();

            $table->foreignId('winner_id')->nullable()->constrained('players', 'id')->nullOnDelete();
            $table->foreignId('loser_id')->nullable()->constrained('players', 'id')->nullOnDelete();
            $table->boolean('is_tie')->nullable();

            $table->integer('round');
            $table->integer('suggested_play_order');
            $table->timestamp('completed_at')->nullable();

            $table->boolean('player1_is_prereq_match_loser')->default(false);
            $table->boolean('player2_is_prereq_match_loser')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_matches', function(Blueprint $table){
            $table->dropForeign(['tournament_id']);
            $table->dropForeign(['player1_id']);
            $table->dropForeign(['player2_id']);
            $table->dropForeign(['winner_id']);
            $table->dropForeign(['loser_id']);
            $table->dropForeign(['player1_prereq_match_id']);
            $table->dropForeign(['player2_prereq_match_id']);
        });
        Schema::dropIfExists('tournament_matches');
    }
};

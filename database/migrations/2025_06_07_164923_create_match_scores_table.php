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
        Schema::create('match_scores', function (Blueprint $table) {
            $table->uuid('id');
            $table->foreignId('tournament_match_id')
                ->constrained('tournament_matches', 'id')
                ->onDelete('cascade');
            $table->foreignId('player_id')
                ->constrained('players', 'id')
                ->onDelete('cascade');
            $table->integer('set')->default(1);
            $table->decimal('score', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['tournament_match_id', 'player_id', 'set']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_scores', function (Blueprint $table) {
            $table->dropForeign(['tournament_match_id']);
            $table->dropForeign(['player_id']);
        });
        Schema::dropIfExists('match_scores');
    }
};

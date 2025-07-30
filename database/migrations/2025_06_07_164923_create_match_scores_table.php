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
            $table->foreignId('tournament_match_id')
                ->constrained('tournament_matches', 'id')
                ->onDelete('cascade');
            $table->foreignId('player_id')
                ->constrained('players', 'id')
                ->onDelete('cascade');
            $table->integer('set')->nullable();
            $table->integer('score')->default(0);
            $table->timestamps();
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

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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('open_id', 50)->unique();
            $table->string('name', 255);
            $table->enum('mode_type', [
                'single_elimination',
                'double_elimination',
                'round_robin',
                'swiss',
                'free_for_all',
            ])->index();
            $table->text('description')->nullable();

            // Date and Time
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();


            $table->boolean('require_check_in')->default(false);
            $table->integer('check_in_time')->nullable();
            $table->unsignedInteger('max_entry')->nullable();

            $table->enum('status', [
                'pending',
                'open',
                'closed',
                'seeding',
                'started',
                'ended'
            ])->default('pending');

            // Double elimination columns
            $table->boolean('split_participant')->nullable();

            // Free for all columns
            $table->integer('ffa_heat_size')->nullable();
            $table->integer('ffa_advance_count')->nullable();

            // Round Robin columns
            $table->integer('head_to_head_count')->nullable();
            $table->string('rank_by', 50)->nullable();

            // Swiss
            $table->decimal('points_per_match_win', 8, 2)->nullable();
            $table->decimal('points_per_match_tie', 8, 2)->nullable();
            $table->decimal('points_per_set_win', 8, 2)->nullable();
            $table->decimal('points_per_set_tie', 8, 2)->nullable();
            $table->decimal('points_per_bye', 8, 2)->nullable();
            $table->integer('swiss_rounds')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};

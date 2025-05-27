<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    protected $table = 'tournament_matches';

    protected $fillable = [
        'tournament_id',
        'state',
        'player1_id',
        'player2_id',
        'player1_score',
        'player2_score',
        'player1_prereq_match_id',
        'player2_prereq_match_id',
        'winner_id',
        'loser_id',
        'is_tie',
        'round',
        'suggested_play_order',
        'completed_at',
        'player1_is_prereq_match_loser',
        'player2_is_prereq_match_loser',
    ];

    protected $casts = [
        'is_tie' => 'boolean',
        'player1_is_prereq_match_loser' => 'boolean',
        'player2_is_prereq_match_loser' => 'boolean',
        'competed_at' => 'datetime',
    ];
}

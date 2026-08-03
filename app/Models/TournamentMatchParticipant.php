<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatchParticipant extends Model
{
    protected $table = 'tournament_match_participants';
    protected $fillable = [
        'tournament_match_id',
        'player_id',
        'position',
        'score',
        'rank',
        'is_winner'
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'is_winner' => 'boolean'
    ];

    public function tournamentMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}

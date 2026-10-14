<?php

namespace App\Bracket\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtTournamentMatchParticipant extends Model
{
    protected $table = 'dt_tournament_match_participants';
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
        return $this->belongsTo(DtTournamentMatch::class, 'tournament_match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(DtPlayer::class, 'player_id');
    }
}

<?php

namespace App\Bracket\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DtMatchScore extends Model
{
    use HasUuids;

    protected $table = 'dt_match_scores';

    protected $fillable = [
        'tournament_match_id',
        'player_id',
        'set',
        'score',
    ];

    protected $casts = [
        'score' => 'decimal:2'
    ];

    public function tournamentMatch()
    {
        return $this->belongsTo(DtTournamentMatch::class, 'tournament_match_id');
    }
    public function player()
    {
        return $this->belongsTo(DtPlayer::class, 'player_id');
    }
}

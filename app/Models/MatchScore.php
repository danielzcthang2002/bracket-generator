<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchScore extends Model
{
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'tournament_match_id',
        'player_id',
        'set',
        'score',
    ];

    public function tournamentMatch()
    {
        return $this->belongsTo(TournamentMatch::class, 'tournament_match_id');
    }
    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id');
    }
}

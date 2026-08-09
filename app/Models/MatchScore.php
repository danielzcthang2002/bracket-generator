<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MatchScore extends Model
{
    use HasUuids;

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
        return $this->belongsTo(TournamentMatch::class, 'tournament_match_id');
    }
    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id');
    }
}

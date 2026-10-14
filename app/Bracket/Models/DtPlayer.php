<?php

namespace App\Bracket\Models;

use Illuminate\Database\Eloquent\Model;

class DtPlayer extends Model
{
    protected $table = 'dt_players';

    protected $fillable = [
        'name',
        'tournament_id',
        'seed',
        'checked_in',
        'checked_in_at',
        'final_rank',
    ];

    protected $casts = [
        'checked_in' => 'boolean',
        'checked_in_at' => 'datetime',
    ];

    // For checked-in players
    public function scopeCheckedIn($query)
    {
        return $query->where('checked_in', true);
    }

    // For not checked-in players
    public function scopeNotCheckedIn($query)
    {
        return $query->where('checked_in', false);
    }

    public function matchesAsPlayer1()
    {
        return $this->hasMany(DtTournamentMatch::class, 'player1_id');
    }

    public function matchesAsPlayer2()
    {
        return $this->hasMany(DtTournamentMatch::class, 'player2_id');
    }

    public function tournament()
    {
        return $this->belongsTo(DtTournament::class, 'tournament_id');
    }

    public function matchScores()
    {
        return $this->hasMany(DtMatchScore::class, 'player_id');
    }
}

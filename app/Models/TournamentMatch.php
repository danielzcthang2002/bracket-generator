<?php

namespace App\Models;

use App\Enums\MatchFormatEnum;
use App\Enums\TournamentMatchStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'match_format'
    ];

    protected $casts = [
        'is_tie' => 'boolean',
        'player1_is_prereq_match_loser' => 'boolean',
        'player2_is_prereq_match_loser' => 'boolean',
        'competed_at' => 'datetime',
        'state' => TournamentMatchStateEnum::class,
        'match_format' => MatchFormatEnum::class,
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }
    public function player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    public function matchScores(): HasMany
    {
        return $this->hasMany(MatchScore::class, 'tournament_match_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentMatchParticipant::class, 'tournament_match_id', 'id');
    }
}

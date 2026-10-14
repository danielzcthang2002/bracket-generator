<?php

namespace App\Bracket\Models;

use App\Bracket\Enums\DtMatchFormatEnum;
use App\Bracket\Enums\DtTournamentMatchStateEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DtTournamentMatch extends Model
{
    protected $table = 'dt_tournament_matches';

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
        'match_format',
    ];

    protected $casts = [
        'is_tie' => 'boolean',
        'player1_is_prereq_match_loser' => 'boolean',
        'player2_is_prereq_match_loser' => 'boolean',
        'completed_at' => 'datetime',
        'state' => DtTournamentMatchStateEnum::class,
        'match_format' => DtMatchFormatEnum::class,
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(DtTournament::class, 'tournament_id');
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(DtPlayer::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(DtPlayer::class, 'player2_id');
    }

    public function matchScores(): HasMany
    {
        return $this->hasMany(DtMatchScore::class, 'tournament_match_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(DtTournamentMatchParticipant::class, 'tournament_match_id', 'id');
    }
}

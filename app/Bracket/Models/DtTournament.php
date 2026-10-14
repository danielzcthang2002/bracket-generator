<?php

namespace App\Bracket\Models;

use App\Bracket\Enums\DtTournamentModeEnum;
use App\Bracket\Enums\DtTournamentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DtTournament extends Model
{

    protected $table = "dt_tournaments";

    protected $fillable = [
        'name',
        'open_id',
        'mode_type',
        'description',
        'start_at',
        'end_at',
        'require_check_in',
        'check_in_time',
        'max_entry',
        'status',
        'split_participant',
        'ffa_heat_size',
        'ffa_advance_count',
        'head_to_head_count',
        'rank_by',
        'points_per_match_win',
        'points_per_match_tie',
        'points_per_set_win',
        'points_per_set_tie',
        'points_per_bye',
        'swiss_rounds'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'require_check_in' => 'boolean',
        'split_participant' => 'boolean',
        'points_per_match_win' => 'decimal:2',
        'points_per_match_tie' => 'decimal:2',
        'points_per_set_win' => 'decimal:2',
        'points_per_set_tie' => 'decimal:2',
        'points_per_bye' => 'decimal:2',
        'status' => DtTournamentStatus::class,
        'mode_type' => DtTournamentModeEnum::class,
    ];


    public function players(): HasMany
    {
        return $this->hasMany(DtPlayer::class, 'tournament_id', 'id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(DtTournamentMatch::class, 'tournament_id', 'id');
    }

    public static function generateOpenId(): string
    {
        return strtoupper(base_convert(bin2hex(random_bytes(9)), 16, 36));
    }
}

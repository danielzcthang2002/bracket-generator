<?php

namespace App\Models;

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{

    protected $table = "tournaments";

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
        'status' => TournamentStatus::class,
        'mode_type' => TournamentModeEnum::class,
    ];


    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'tournament_id', 'id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'tournament_id', 'id');
    }

    public static function generateOpenId(): string
    {
        return strtoupper(base_convert(bin2hex(random_bytes(9)), 16, 36));
    }
}

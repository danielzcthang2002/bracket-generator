<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';

    protected $fillable = [
        'name',
        'tournament_id',
        'seed',
        'checked_in',
        'checked_in_at'
    ];

    protected $casts = [
        'checked_in' => 'boolean',
        'checked_in_at' => 'datetime',
    ];
}

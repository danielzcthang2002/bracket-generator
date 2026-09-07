<?php

namespace App\Services\Bracket\Contracts;

use App\Models\Tournament;

interface HasMode
{
    public function initialize(Tournament $tournament);
}

<?php

namespace App\Services\Bracket\Contracts;

use App\Models\Tournament;

interface HasMode
{
    /** Whether the checked-in roster meets this mode's start requirements. */
    public function canStart(Tournament $tournament): bool;

    public function initialize(Tournament $tournament);

    public function previewBracket(Tournament $tournament): array;
}

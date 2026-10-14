<?php

namespace App\Bracket\Contracts;

use App\Bracket\Models\DtTournament;

interface DtHasMode
{
    /** Whether the checked-in roster meets this mode's start requirements. */
    public function canStart(DtTournament $tournament): bool;

    public function initialize(DtTournament $tournament);

    public function previewBracket(DtTournament $tournament): array;
}

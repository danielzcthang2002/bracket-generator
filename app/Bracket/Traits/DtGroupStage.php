<?php

namespace App\Bracket\Traits;

use App\Bracket\Models\DtTournament;
use Illuminate\Support\Collection;

trait DtGroupStage
{
    public function initializeGroupStage(DtTournament $tournament) {}

    public function summarizeParticipantGroups(Collection $players, int $total, int $perGroup){}
}

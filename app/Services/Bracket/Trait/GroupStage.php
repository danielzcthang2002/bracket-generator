<?php

namespace App\Services\Bracket\Trait;

use App\Models\Tournament;
use Illuminate\Support\Collection;

trait GroupStage
{
    public function initializeGroupStage(Tournament $tournament) {}

    public function summarizeParticipantGroups(Collection $players, int $total, int $perGroup){}
}

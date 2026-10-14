<?php

namespace App\Bracket\Contracts;

use App\Bracket\Models\DtTournament;
use Illuminate\Support\Collection;

interface DtHasGroupStage
{
    public function initializeGroupStage(DtTournament $tournament):void;

    public function summarizeParticipantGroups(Collection $players, int $total, int $perGroup): array;

    public function endGroupStage(DtTournament $tournament): void;

    public function moveToFinalStage(Collection $winners): void;
}

<?php

namespace App\Services\Bracket\Contracts;

use App\Models\Tournament;
use Illuminate\Support\Collection;

interface HasGroupStage
{
    public function initializeGroupStage(Tournament $tournament):void;

    public function summarizeParticipantGroups(Collection $players, int $total, int $perGroup): array;

    public function endGroupStage(Tournament $tournament): void;

    public function moveToFinalStage(Collection $winners): void;
}

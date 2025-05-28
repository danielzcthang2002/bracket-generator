<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\Bracket\SingleEliminationService;

class TournamentMatchService
{
    private SingleEliminationService $singleEliminationService;

    public function __construct()
    {
        $this->singleEliminationService = new SingleEliminationService();
    }

    public function generateMatches(int $tournamentId)
    {
        $tournament = Tournament::findOrFail($tournamentId);

        $tournamentMode = $tournament->mode_type->value;

        switch ($tournamentMode) {
            case 'single_elimination':
                return $this->singleEliminationService->initialize($tournament);
            case 'double_elimination':
                return [];
            case 'round_robin':
                return [];
            default:
                throw new \Exception('Unsupported tournament mode: ' . $tournamentMode);
        }
    }
}

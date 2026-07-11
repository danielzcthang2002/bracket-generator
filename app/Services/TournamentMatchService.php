<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TournamentMatchStateEnum;
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

    public function updateMatchScores(int $matchId, string $scoresCsv, $winnerId): TournamentMatch
    {
        $match = TournamentMatch::findOrFail($matchId);
        $player1Id = $match->player1_id;
        $player2Id = $match->player2_id;

        if ($player1Id === null || $player2Id === null) {
            throw new \InvalidArgumentException('Match must have two players before scores can be submitted.');
        }

        if ($winnerId !== null && !in_array($winnerId, [$player1Id, $player2Id])) {
            throw new \InvalidArgumentException('Winner ID must be one of the match players. ');
        }

        $sets = array_values(array_filter(explode(',', rtrim($scoresCsv, ',')), fn ($set) => trim($set) !== ''));

        if (count($sets) === 0) {
            throw new \InvalidArgumentException('At least one set score is required.');
        }

        $scoreUpsertData = [];
        $totalPlayer1Score = 0;
        $totalPlayer2Score = 0;

        $match->matchScores()->delete();
        foreach ($sets as $key => $value) {
            $scores = explode('-', $value);

            if (count($scores) !== 2) {
                throw new \InvalidArgumentException('Invalid scores format. Expected format: "player1_score-player2_score".');
            }

            $player1_score = (int)$scores[0];
            $player2_score = (int)$scores[1];
            $totalPlayer1Score += $player1_score;
            $totalPlayer2Score += $player2_score;

            $scoreUpsertData[] = [
                'tournament_match_id' => $matchId,
                'player_id' => $player1Id,
                'set' => $key + 1,
                'score' => $player1_score,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $scoreUpsertData[] = [
                'tournament_match_id' => $matchId,
                'player_id' => $player2Id,
                'set' => $key + 1,
                'score' => $player2_score,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $match->matchScores()->insert(
            $scoreUpsertData
        );

        $match->player1_score = $totalPlayer1Score;
        $match->player2_score = $totalPlayer2Score;

        if ($winnerId) {
            $match->winner_id = $winnerId;
            $match->loser_id = $winnerId === $match->player1_id ? $match->player2_id : $match->player1_id;
            $match->state = TournamentMatchStateEnum::COMPLETE;
        }

        $match->save();

        if ($winnerId) {
            $this->generateMatches($match->tournament_id);
        }


        return $match;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TournamentMatchStateEnum;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentMatchParticipant;
use App\Services\Bracket\DoubleEliminationService;
use App\Services\Bracket\FreeForAllService;
use App\Services\Bracket\RoundRobinService;
use App\Services\Bracket\SingleEliminationService;
use App\Services\Bracket\SwissService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TournamentMatchService
{
    private SingleEliminationService $singleEliminationService;
    private DoubleEliminationService $doubleEliminationService;
    private RoundRobinService $roundRobinService;
    private SwissService $swissService;
    private FreeForAllService $freeforallService;

    public function __construct()
    {
        $this->singleEliminationService = new SingleEliminationService();
        $this->doubleEliminationService = new DoubleEliminationService();
        $this->roundRobinService = new RoundRobinService();
        $this->swissService = new SwissService();
        $this->freeforallService = new FreeForAllService();
    }

    /**
     * Generates matches for a given tournament based on its mode.
     *
     * @param int $tournamentId The ID of the tournament to generate matches for.
     * @return Collection Returns the generated matches or an empty array if the tournament mode is not supported.
     */
    public function generateMatches(int $tournamentId): Collection
    {
        $tournament = Tournament::findOrFail($tournamentId);

        $tournamentMode = $tournament->mode_type->value;

        switch ($tournamentMode) {
            case 'single_elimination':
                return $this->singleEliminationService->initialize($tournament);
            case 'double_elimination':
                return $this->doubleEliminationService->initialize($tournament);
            case 'round_robin':
                return $this->roundRobinService->initialize($tournament);
            case 'swiss':
                return $this->swissService->initialize($tournament);
            case 'free_for_all':
                return $this->freeforallService->initialize($tournament);
            default:
                throw new \Exception('Unsupported tournament mode: ' . $tournamentMode);
        }
    }

    /**
     * Updates the scores for a given match and determines the winner.
     *
     * @param int $matchId The ID of the match to update scores for.
     * @param string $scoresCsv A CSV string representing the scores for each set, e.g., "10-5,7-6".
     * @param int|null $winnerId The ID of the player who won the match. If null, no winner is set. 'tie' for tie match
     * @return TournamentMatch The updated match with the new scores and optionally a winner.
     */
    public function updateMatchScores(int $matchId, string $scoresCsv, string|int|null $winnerId): TournamentMatch
    {
        $match = TournamentMatch::findOrFail($matchId);
        $player1Id = $match->player1_id;
        $player2Id = $match->player2_id;

        if ($player1Id === null || $player2Id === null) {
            throw new \InvalidArgumentException('Match must have two players before scores can be submitted.');
        }

        $isTie = $winnerId === 'tie';
        $winnerId = $winnerId !== null && $winnerId !== 'tie' ? (int) $winnerId : null;

        if (!$isTie && $winnerId !== null && !in_array($winnerId, [$player1Id, $player2Id], true)) {
            throw new \InvalidArgumentException('Winner ID must be one of the match players. ');
        }

        $sets = array_values(array_filter(explode(',', rtrim($scoresCsv, ',')), fn($set) => trim($set) !== ''));

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
                'id' => Str::uuid(),
                'tournament_match_id' => $matchId,
                'player_id' => $player1Id,
                'set' => $key + 1,
                'score' => $player1_score,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $scoreUpsertData[] = [
                'id' => Str::uuid(),
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
        $match->winner_id = null;
        $match->loser_id = null;
        $match->is_tie = $isTie;

        if ($isTie) {
            $match->state = TournamentMatchStateEnum::COMPLETE;
        } elseif ($winnerId !== null) {
            $match->winner_id = $winnerId;
            $match->loser_id = $winnerId === $match->player1_id ? $match->player2_id : $match->player1_id;
            $match->state = TournamentMatchStateEnum::COMPLETE;
        }

        $match->save();

        if ($winnerId !== null || $isTie) {
            $this->generateMatches($match->tournament_id);
        }


        return $match;
    }

    /**
     * Updates scores for a free-for-all match by writing the final participant
     * order directly onto the participant rows.
     *
     * @param array<int, array<string, mixed>> $participantsData
     */
    public function updateFfaMatchScores(int $matchId, array $participantsData): TournamentMatch
    {
        $match = TournamentMatch::query()->with('participants')->findOrFail($matchId);
        $participants = $match->participants;

        if ($participants->isEmpty()) {
            throw new \InvalidArgumentException('Free-for-all match must have participants before scores can be submitted.');
        }

        $participantsById = $participants->keyBy('id');
        $submittedParticipantIds = collect($participantsData)
            ->pluck('id')
            ->map(fn($participantId) => (int) $participantId)
            ->values();

        if ($submittedParticipantIds->count() !== $participants->count() || $submittedParticipantIds->diff($participantsById->keys())->isNotEmpty()) {
            throw new \InvalidArgumentException('Submitted participants must match the match roster exactly.');
        }

        $normalizedParticipants = collect($participantsData)->values()->map(function (array $participantData, int $index) use ($participantsById): array {
            $participantId = (int) ($participantData['id'] ?? 0);
            $participant = $participantsById->get($participantId);

            if ($participant === null) {
                throw new \InvalidArgumentException('Submitted participants must match the match roster exactly.');
            }

            $rank = $participantData['rank'] ?? null;
            $rank = $rank === '' || $rank === null ? null : (int) $rank;

            $score = $participantData['score'] ?? null;
            $score = $score === '' || $score === null ? null : (float) $score;

            return [
                'participant' => $participant,
                'rank' => $rank,
                'score' => $score,
                'index' => $index,
            ];
        });

        $normalizedParticipants = $normalizedParticipants->map(function (array $participantData): array {
            $participantData['rank'] = $participantData['rank'] ?? ($participantData['index'] + 1);
            unset($participantData['index']);

            return $participantData;
        });

        $submittedRanks = $normalizedParticipants->pluck('rank');

        if ($submittedRanks->contains(fn($rank) => !is_int($rank) || $rank < 1)) {
            throw new \InvalidArgumentException('Each participant must have a valid rank.');
        }

        if ($submittedRanks->unique()->count() !== $participants->count()) {
            throw new \InvalidArgumentException('Each participant rank must be unique.');
        }

        DB::transaction(function () use ($normalizedParticipants): void {
            foreach ($normalizedParticipants as $participantData) {
                /** @var TournamentMatchParticipant $participant */
                $participant = $participantData['participant'];

                $participant->update([
                    'rank' => $participantData['rank'],
                    'score' => $participantData['score'],
                    'is_winner' => $participantData['rank'] === 1,
                ]);
            }
        });

        $match->forceFill([
            'state' => TournamentMatchStateEnum::COMPLETE,
            'is_tie' => false,
            'winner_id' => null,
            'loser_id' => null,
        ]);
        $match->save();

        $this->generateMatches($match->tournament_id);

        return $match->refresh()->load(['participants.player']);
    }
}

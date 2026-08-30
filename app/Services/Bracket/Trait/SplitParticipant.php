<?php

declare(strict_types=1);

namespace App\Services\Bracket\Trait;

use App\Models\Tournament;
use App\Models\TournamentMatch;

trait SplitParticipant
{
    /**
     * Half of the next power of two at or above $numPlayers.
     */
    private function halfOfNextPowerOfTwo(int $numPlayers): int
    {
        if ($numPlayers < 1) {
            return 0;
        }

        $nextPowerOfTwo = $numPlayers <= 1 ? 1 : (int) pow(2, ceil(log($numPlayers, 2)));

        return max(1, (int) ($nextPowerOfTwo / 2));
    }

    /**
     * @return array{0: TournamentMatch[], 1: array} [matches, carryEntrants]
     */
    private function buildSplitLbRoundOne(
        Tournament $tournament,
        $bottomPlayers,
        int $round,
        int &$playOrder,
        array $wb1Losers,
    ): array {
        $bottomIds = $bottomPlayers->pluck('id')->values();
        $bottomCount = $bottomIds->count();
        $wbLoserCount = count($wb1Losers);

        $matches = [];
        $carry = [];

        if ($wbLoserCount >= $bottomCount) {
            for ($i = 0; $i < $bottomCount; $i++) {
                $prereqMatchId = $wb1Losers[$i]->id;

                $match = TournamentMatch::updateOrCreate([
                    'tournament_id' => $tournament->id,
                    'round' => -$round,
                    'suggested_play_order' => $playOrder,
                ], [
                    'player1_id' => $this->resolvePlayer($bottomIds[$i], null, false),
                    'player2_id' => $this->resolvePlayer(null, $prereqMatchId, true),
                    'player1_prereq_match_id' => null,
                    'player2_prereq_match_id' => $prereqMatchId,
                    'player1_is_prereq_match_loser' => false,
                    'player2_is_prereq_match_loser' => true,
                ]);

                $this->rememberOutcome($match);
                $matches[] = $match;
                $playOrder++;
            }

            for ($i = $bottomCount; $i < $wbLoserCount; $i++) {
                $carry[] = ['match' => $wb1Losers[$i]->id, 'loser' => true];
            }
        } else {
            $diff = $bottomCount - $wbLoserCount;
            $pairs = min(intdiv($bottomCount, 2), $diff);

            for ($i = 0; $i < $pairs; $i++) {
                $match = TournamentMatch::updateOrCreate([
                    'tournament_id' => $tournament->id,
                    'round' => -$round,
                    'suggested_play_order' => $playOrder,
                ], [
                    'player1_id' => $this->resolvePlayer($bottomIds[$i * 2], null, false),
                    'player2_id' => $this->resolvePlayer($bottomIds[$i * 2 + 1], null, false),
                    'player1_prereq_match_id' => null,
                    'player2_prereq_match_id' => null,
                    'player1_is_prereq_match_loser' => false,
                    'player2_is_prereq_match_loser' => false,
                ]);

                $this->rememberOutcome($match);
                $matches[] = $match;
                $playOrder++;
            }

            for ($i = $pairs * 2; $i < $bottomCount; $i++) {
                $carry[] = ['raw' => $bottomIds[$i]];
            }

            foreach ($wb1Losers as $match) {
                $carry[] = ['match' => $match->id, 'loser' => true];
            }
        }

        return [$matches, $carry];
    }
}

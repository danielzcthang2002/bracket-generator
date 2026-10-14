<?php

namespace App\Bracket\Traits;

use App\Bracket\Enums\DtTournamentMatchStateEnum;
use App\Bracket\Models\DtTournamentMatch;

trait DtResetsDependentMatches
{
    public function resetDependentMatches(DtTournamentMatch $match): void
    {
        $pendingIds = [$match->id];
        $visitedIds = [$match->id];

        while ($pendingIds !== []) {
            $dependents = DtTournamentMatch::where('tournament_id', $match->tournament_id)
                ->whereNotIn('id', $visitedIds)
                ->where(fn ($query) => $query->whereIn('player1_prereq_match_id', $pendingIds)
                    ->orWhereIn('player2_prereq_match_id', $pendingIds))
                ->get();

            foreach ($dependents as $dependent) {
                $dependent->matchScores()->delete();
                $dependent->update([
                    'winner_id' => null,
                    'loser_id' => null,
                    'player1_score' => null,
                    'player2_score' => null,
                    'is_tie' => false,
                    'completed_at' => null,
                    'state' => DtTournamentMatchStateEnum::PENDING,
                ]);
            }

            $pendingIds = $dependents->modelKeys();
            $visitedIds = array_merge($visitedIds, $pendingIds);
        }
    }

}

<?php

declare(strict_types=1);

namespace App\Bracket\Services;

use App\Bracket\Enums\DtTournamentMatchStateEnum;
use App\Bracket\Models\DtTournament;
use App\Bracket\Models\DtTournamentMatch;
use App\Bracket\Models\DtTournamentMatchParticipant;
use App\Bracket\Contracts\DtHasMode;
use Illuminate\Support\Collection;

/**
 * Free-for-all bracket generation.
 *
 * Unlike single/double elimination (1v1 matches, decided by winner_id) and
 * Swiss (1v1 matches, decided by winner_id + tiebreak points), a free-for-all
 * match is a "heat" of N players who all compete together and finish in a
 * directly-reported rank order (1st, 2nd, 3rd, ...) stored per player on
 * DtTournamentMatchParticipant. There's no player1_id/player2_id/winner_id on
 * these matches - the roster lives entirely in the participants table.
 *
 * Like DtSwissService, a free-for-all bracket can't be generated all at once:
 * round N+1's heats depend on the *results* of round N (only the top
 * finishers of each heat advance). So initialize() is designed to be called
 * repeatedly over the life of the event. It reconciles reachable rounds
 * against their qualifying order, preserving unchanged heats. Changed
 * rosters or slots clear that heat and its dependent results. Further
 * rosters wait until the preceding round is fully reported again.
 *
 * Heats:
 *   - Players are split into heats of `ffa_heat_size` (tournament attribute,
 *     default 4, minimum 2) using a snake/serpentine draft off seed order
 *     (round 1) or off the previous round's placements (round 2+), so
 *     strength is spread evenly across heats rather than front-loaded into
 *     the first one. This is a simplified heuristic, not a full balanced
 *     scheduler - fine for the common case of roughly-even heat counts.
 *   - If the remaining player pool already fits in a single heat, that heat
 *     *is* the final: everyone plays together and the reported ranks are
 *     the tournament's final standings for that group.
 *   - A heat that ends up with exactly one player (only possible when the
 *     pool can't fill even one full heat) is an automatic bye: recorded as
 *     an immediate rank-1 win, no real contest needed.
 *
 * Advancement:
 *   - `ffa_advance_count` (tournament attribute, default half the heat
 *     size, rounded down) is how many players advance out of each heat.
 *     It's clamped to [1, heat size - 1] so every non-bye heat always
 *     shrinks the field, and further clamped per-heat to heat size - 1 for
 *     any undersized remainder heat (so a 2-player heat is effectively
 *     sudden death: only the winner moves on).
 *
 * Final ranks:
 *   - The final heat's reported ranks become the top of the standings
 *     directly.
 *   - Every earlier round fills the next rank tier with the players who did
 *     NOT advance out of it, most-recently-eliminated first (round
 *     totalRounds-1 down to round 1) - the same "how far did you get"
 *     principle DtSingleEliminationService uses, just with finer-grained
 *     tiers since a heat can eliminate several players at once. Within a
 *     tier, players are ordered by their in-heat rank, then by seed as a
 *     cross-heat tiebreak.
 *
 * NOTE: this service assumes DtTournamentMatch has a `participants()`
 * hasMany(DtTournamentMatchParticipant::class) relation. Add it if it isn't
 * there yet - everything below depends on it.
 */
class DtFreeForAllService extends DtModeService implements DtHasMode
{
    public function canStart(DtTournament $tournament): bool
    {
        return $tournament->players()->checkedIn()->count() >= 2;
    }

    private const DEFAULT_HEAT_SIZE = 4;
    private const DEFAULT_ADVANCE_RATIO = 0.5;

    public function previewBracket(DtTournament $tournament): array
    {
        $players = $this->getPlayers($tournament);
        if ($players->count() < 2) {
            return [];
        }

        $preview = [1 => []];
        foreach ($this->buildHeats($players, $this->heatSize($tournament)) as $index => $heat) {
            $preview[1][] = [
                'round' => 1,
                'suggested_play_order' => $index + 1,
                'participants' => array_map(fn ($player) => $this->previewPlayer($player), $heat),
            ];
        }

        return $preview;
    }

    public function initialize(DtTournament $tournament)
    {
        $players = $this->getPlayers($tournament);
        $numPlayers = $players->count();

        if ($numPlayers < 2) {
            $tournament->matches()->delete();
            $tournament->players()->update(['final_rank' => null]);
            if ($numPlayers === 1) {
                $tournament->players()->find($players->first()->id)?->update(['final_rank' => 1]);
            }
            return collect();
        }

        $heatSize = $this->heatSize($tournament);
        $advanceCount = $this->advanceCount($tournament, $heatSize);
        $tournamentComplete = false;
        $ordered = $players;
        $round = 1;

        // Reconcile each reachable round against the corrected qualifying order.
        while (true) {
            $this->generateRound($tournament, $round, $ordered, $heatSize);
            if (!$this->roundIsComplete($tournament, $round)) {
                break;
            }
            if ($ordered->count() <= $heatSize) {
                $tournamentComplete = true;
                break;
            }
            $advancers = $this->computeAdvancers($tournament, $round, $advanceCount);
            $ordered = $this->orderByPerformance($tournament, $advancers, $round);
            $round++;
        }
        $this->updateMatchesState($tournament);

        if ($tournamentComplete) {
            $this->assignFinalRanks($tournament, $heatSize);
        } else {
            $tournament->players()->update(['final_rank' => null]);
        }

        return $tournament->matches()
            ->orderBy('suggested_play_order', 'asc')
            ->with(['participants.player'])
            ->get();
    }

    private function heatSize(DtTournament $tournament): int
    {
        $configured = (int) ($tournament->ffa_heat_size ?? self::DEFAULT_HEAT_SIZE);
        return max(2, $configured);
    }

    /**
     * How many players advance out of a *full* heat. Clamped so a heat
     * always loses at least one player and never advances everyone.
     */
    private function advanceCount(DtTournament $tournament, int $heatSize): int
    {
        $configured = (int) ($tournament->ffa_advance_count ?? floor($heatSize * self::DEFAULT_ADVANCE_RATIO));
        return min(max(1, $configured), $heatSize - 1);
    }

    /**
     * Create every heat (and any bye row) for a single round.
     * $orderedPlayers must already be in the order heat-building should
     * walk: seed order for round 1, prior-round performance for round 2+.
     */
    private function generateRound(DtTournament $tournament, int $round, Collection $orderedPlayers, int $heatSize): void
    {
        $playOrder = ((int) $tournament->matches()->where('round', '<', $round)->max('suggested_play_order')) + 1;
        $heats = $this->buildHeats($orderedPlayers, $heatSize);

        foreach ($heats as $heatPlayers) {
            $match = DtTournamentMatch::updateOrCreate([
                'tournament_id' => $tournament->id,
                'round' => $round,
                'suggested_play_order' => $playOrder,
            ], [
                'player1_id' => null,
                'player2_id' => null,
                'player1_prereq_match_id' => null,
                'player2_prereq_match_id' => null,
            ]);

            $roster = $match->participants()->orderBy('position')->pluck('player_id')->all();
            $expectedRoster = array_map(fn ($player) => $player->id, $heatPlayers);
            if ($roster !== $expectedRoster) {
                $this->invalidateFollowingHeats($tournament, $match);
                $this->clearHeat($match);
            }

            foreach ($heatPlayers as $index => $player) {
                DtTournamentMatchParticipant::updateOrCreate([
                    'tournament_match_id' => $match->id,
                    'player_id' => $player->id,
                ], [
                    'position' => $index + 1,
                ]);
            }

            // A heat with only one player (only possible when the pool
            // can't fill even one full heat) is an automatic bye.
            if (count($heatPlayers) === 1) {
                DtTournamentMatchParticipant::query()
                    ->where('tournament_match_id', $match->id)
                    ->where('player_id', $heatPlayers[0]->id)
                    ->update(['rank' => 1, 'is_winner' => true]);

                $match->update(['state' => DtTournamentMatchStateEnum::COMPLETE]);
            }

            $playOrder++;
        }
    }

    /**
     * Split an ordered player list into heats of (at most) $heatSize using
     * a snake/serpentine draft, so strength is spread across heats instead
     * of stacked into the first ones: player 1 -> heat 1, player 2 -> heat
     * 2, ..., last heat, then back down again, repeat.
     *
     * @return array<int, array<int, \App\Bracket\Models\DtPlayer>>
     */
    private function buildHeats(Collection $orderedPlayers, int $heatSize): array
    {
        $players = $orderedPlayers->values();
        $numPlayers = $players->count();

        if ($numPlayers <= $heatSize) {
            return [$players->all()];
        }

        $numHeats = (int) ceil($numPlayers / $heatSize);
        $heats = array_fill(0, $numHeats, []);

        $heatIndex = 0;
        $direction = 1;

        foreach ($players as $player) {
            $heats[$heatIndex][] = $player;

            $next = $heatIndex + $direction;
            if ($next < 0 || $next >= $numHeats) {
                $direction *= -1;
            } else {
                $heatIndex = $next;
            }
        }

        return $heats;
    }

    private function roundIsComplete(DtTournament $tournament, int $round): bool
    {
        $matchIds = $tournament->matches()->where('round', $round)->pluck('id');

        if ($matchIds->isEmpty()) {
            return false;
        }

        return !DtTournamentMatchParticipant::query()
            ->whereIn('tournament_match_id', $matchIds, 'and', false)
            ->whereNull('rank', 'and', false)
            ->exists();
    }

    private function clearHeat(DtTournamentMatch $match): void
    {
        $match->participants()->delete();
        $match->matchScores()->delete();
        $match->update([
            'state' => DtTournamentMatchStateEnum::PENDING,
            'completed_at' => null,
            'winner_id' => null,
            'loser_id' => null,
            'is_tie' => false,
            'player1_score' => null,
            'player2_score' => null,
        ]);
    }

    private function invalidateFollowingHeats(DtTournament $tournament, DtTournamentMatch $match): void
    {
        $participants = $match->participants()->orderBy('rank')->get();
        $count = $participants->count();
        $take = $count <= 1 ? $count : min($this->advanceCount($tournament, $this->heatSize($tournament)), $count - 1);
        $qualifiers = $participants->whereNotNull('rank')->take($take)->pluck('player_id');
        if ($qualifiers->isEmpty()) {
            return;
        }

        $dependents = $tournament->matches()->where('round', $match->round + 1)
            ->whereHas('participants', fn ($query) => $query->whereIn('player_id', $qualifiers))->get();
        foreach ($dependents as $dependent) {
            $this->invalidateFollowingHeats($tournament, $dependent);
            $this->clearHeat($dependent);
        }
    }

    /**
     * Top finishers of every heat in $round, clamped per-heat so an
     * undersized remainder heat still loses at least one player.
     *
     * @return Collection<int, int> advancing player ids
     */
    private function computeAdvancers(DtTournament $tournament, int $round, int $advanceCount): Collection
    {
        $matches = $tournament->matches()->where('round', $round)->with('participants')->get();
        $advancerIds = collect();

        foreach ($matches as $match) {
            $count = $match->participants->count();
            $take = $count <= 1 ? $count : max(1, min($advanceCount, $count - 1));

            $advancerIds = $advancerIds->merge(
                $match->participants->sortBy('rank')->take($take)->pluck('player_id')
            );
        }

        return $advancerIds->values();
    }

    /**
     * Order advancing players by how they just finished (best in-heat rank
     * first), tiebroken by seed, so the next round's heats are built off
     * current standing rather than raw seed.
     */
    private function orderByPerformance(DtTournament $tournament, Collection $advancerIds, int $round): Collection
    {
        $ranks = DtTournamentMatchParticipant::query()
            ->whereIn('player_id', $advancerIds, 'and', false)
            ->whereHas('tournamentMatch', function ($q) use ($tournament, $round) {
                $q->where('tournament_id', $tournament->id)->where('round', $round);
            })
            ->pluck('rank', 'player_id');

        $seeds = $tournament->players()->whereIn('id', $advancerIds)->pluck('seed', 'id');

        return $this->getPlayers($tournament, $advancerIds->all())
            ->sortBy(function ($player) use ($ranks, $seeds) {
                return [
                    $ranks[$player->id] ?? PHP_INT_MAX,
                    $seeds[$player->id] ?? PHP_INT_MAX,
                ];
            })
            ->values();
    }

    private function updateMatchesState(DtTournament $tournament): void
    {
        $matches = $tournament->matches()->with('participants')->get();

        foreach ($matches as $match) {
            if ($match->participants->isEmpty()) {
                continue;
            }

            $allRanked = $match->participants->every(fn($p) => $p->rank !== null);

            $match->update([
                'state' => $allRanked ? DtTournamentMatchStateEnum::COMPLETE : DtTournamentMatchStateEnum::OPEN,
            ]);
        }
    }

    private function assignFinalRanks(DtTournament $tournament, int $heatSize): void
    {
        $matches = $tournament->matches()->with('participants')->get();
        $totalRounds = (int) $matches->max('round');

        if ($totalRounds === 0) {
            return;
        }

        $finalHeat = $matches->where('round', $totalRounds)->first();

        if ($finalHeat === null || $finalHeat->participants->contains(fn($p) => $p->rank === null)) {
            return;
        }

        $seeds = $tournament->players()->pluck('seed', 'id');
        $rank = 1;

        // The final heat's ranks are already the top of the standings.
        foreach ($finalHeat->participants->sortBy('rank') as $participant) {
            $tournament->players()->find($participant->player_id)?->update(['final_rank' => $rank]);
            $rank++;
        }

        // Walk earlier rounds from most-recently-eliminated to least:
        // everyone who did NOT advance out of round $round fills the next
        // rank tier, ordered by in-heat rank then seed.
        for ($round = $totalRounds - 1; $round >= 1; $round--) {
            $roundMatches = $matches->where('round', $round);
            if ($roundMatches->isEmpty()) {
                continue;
            }

            $advanceCount = $this->advanceCount($tournament, $heatSize);
            $advancerIds = $this->computeAdvancers($tournament, $round, $advanceCount)->flip();

            $eliminated = collect();
            foreach ($roundMatches as $match) {
                foreach ($match->participants as $participant) {
                    if (!$advancerIds->has($participant->player_id)) {
                        $eliminated->push($participant);
                    }
                }
            }

            $ordered = $eliminated->sortBy(function ($p) use ($seeds) {
                return [
                    $p->rank ?? PHP_INT_MAX,
                    $seeds[$p->player_id] ?? PHP_INT_MAX,
                ];
            });

            foreach ($ordered as $participant) {
                $tournament->players()->find($participant->player_id)?->update(['final_rank' => $rank]);
                $rank++;
            }
        }
    }
}

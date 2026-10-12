<?php

declare(strict_types=1);

namespace App\Services\Bracket;

use App\Enums\TournamentMatchStateEnum;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\Bracket\Contracts\HasMode;
use App\Services\Bracket\Trait\SplitParticipant;

class DoubleEliminationService extends ModeService implements HasMode
{
    use SplitParticipant;

    /**
     * Build the same schedule as initialize(), using play order numbers as
     * prerequisite references. No match or participant rows are written.
     */
    public function previewBracket(Tournament $tournament): array
    {
        $players = $this->getPlayers($tournament);
        if ($players->count() < 2) {
            return [];
        }

        $byId = $players->keyBy('id');
        $split = (bool) $tournament->split_participant;
        $topCount = $split ? min($players->count(), $this->halfOfNextPowerOfTwo($players->count())) : $players->count();
        $top = $players->take($topCount)->values();
        $bottom = $players->slice($topCount)->values();
        $wbRoundCount = $this->calculateTotalRound($top->count());
        $preview = [];
        $wbMatches = [];
        $queue = collect();
        $playOrder = 1;

        for ($round = 1; $round <= $wbRoundCount; $round++) {
            $matchCount = $round === 1
                ? (int) $this->firstRoundMatches($top->count())
                : $this->calculateMatchesCountInRound($wbRoundCount, $round);
            $entries = $this->filterPlayersByRound($top, $matchCount)->values();
            $wbMatches[$round] = [];
            for ($i = 0; $i < $entries->count(); $i += 2) {
                $first = $entries[$i]['id'] == 0
                    ? ['match' => $queue->shift(), 'loser' => false]
                    : ['raw' => $entries[$i]['id']];
                $second = $entries[$i + 1]['id'] == 0
                    ? ['match' => $queue->shift(), 'loser' => false]
                    : ['raw' => $entries[$i + 1]['id']];
                $order = $this->appendPreviewMatch($preview, $round, $playOrder, $first, $second, $byId);
                $wbMatches[$round][] = $order;
                $queue->push($order);
            }
        }

        $champion = null;
        if ($split && $bottom->isNotEmpty()) {
            $round = 1;
            $wbLosers = array_map(fn ($order) => ['match' => $order, 'loser' => true], $wbMatches[1] ?? []);
            $bottomIds = $bottom->pluck('id')->values()->all();
            $carry = [];
            $survivors = [];
            if (count($wbLosers) >= count($bottomIds)) {
                foreach ($bottomIds as $index => $id) {
                    $order = $this->appendPreviewMatch($preview, -$round, $playOrder, ['raw' => $id], $wbLosers[$index], $byId);
                    $survivors[] = ['match' => $order, 'loser' => false];
                }
                $carry = array_slice($wbLosers, count($bottomIds));
            } else {
                $pairs = min(intdiv(count($bottomIds), 2), count($bottomIds) - count($wbLosers));
                for ($i = 0; $i < $pairs; $i++) {
                    $order = $this->appendPreviewMatch($preview, -$round, $playOrder,
                        ['raw' => $bottomIds[$i * 2]], ['raw' => $bottomIds[$i * 2 + 1]], $byId);
                    $survivors[] = ['match' => $order, 'loser' => false];
                }
                $carry = array_merge(array_map(fn ($id) => ['raw' => $id], array_slice($bottomIds, $pairs * 2)), $wbLosers);
            }

            for ($k = 2; $k <= $wbRoundCount; $k++) {
                $round++;
                [$survivors, $carry] = $this->appendPreviewLbRound($preview, $round, $playOrder, array_merge($survivors, $carry), [], $byId);
                $round++;
                $incoming = array_map(fn ($order) => ['match' => $order, 'loser' => true], $wbMatches[$k] ?? []);
                [$survivors, $carry] = $this->appendPreviewLbRound($preview, $round, $playOrder, array_merge($survivors, $carry), $incoming, $byId);
            }
            while (count($survivors) + count($carry) > 1) {
                $round++;
                [$survivors, $carry] = $this->appendPreviewLbRound($preview, $round, $playOrder, array_merge($survivors, $carry), [], $byId);
            }
            $champion = $survivors[0] ?? $carry[0] ?? null;
        } else {
            $round = 0;
            $population = array_map(fn ($order) => ['match' => $order, 'loser' => true], $wbMatches[1] ?? []);
            for ($k = 2; $k <= $wbRoundCount; $k++) {
                $incoming = array_map(fn ($order) => ['match' => $order, 'loser' => true], $wbMatches[$k] ?? []);
                if (count($population) > count($incoming)) {
                    $round++;
                    [$winners, $carry] = $this->appendPreviewLbRound($preview, $round, $playOrder, $population, [], $byId,
                        count($population) - count($incoming));
                    $population = array_merge($winners, $carry);
                }
                $round++;
                [$winners, $carry] = $this->appendPreviewLbRound($preview, $round, $playOrder, $population, array_reverse($incoming), $byId);
                $population = array_merge($winners, $carry);
            }
            while (count($population) > 1) {
                $round++;
                [$winners, $carry] = $this->appendPreviewLbRound($preview, $round, $playOrder, $population, [], $byId);
                $population = array_merge($winners, $carry);
            }
            $champion = $population[0] ?? null;
        }

        if ($champion !== null && isset($wbMatches[$wbRoundCount][0])) {
            $this->appendPreviewMatch($preview, $wbRoundCount + 1, $playOrder,
                ['match' => $wbMatches[$wbRoundCount][0], 'loser' => false], $champion, $byId);
            // The reset is conditional, so it is a note in the UI rather than
            // a scheduled match (initialize creates it only after an upset).
        }

        ksort($preview);
        return $preview;
    }

    private function appendPreviewMatch(array &$preview, int $round, int &$playOrder, array $first, array $second, $byId): int
    {
        $order = $playOrder++;
        $preview[$round][] = [
            'round' => $round,
            'suggested_play_order' => $order,
            'player1' => $this->previewEntrant($first, $byId),
            'player2' => $this->previewEntrant($second, $byId),
        ];
        return $order;
    }

    private function previewEntrant(array $entrant, $byId): array
    {
        if (isset($entrant['raw'])) {
            return $this->previewPlayer($byId[$entrant['raw']]);
        }
        $kind = ($entrant['loser'] ?? false) ? 'Loser' : 'Winner';
        return $this->previewTbd("{$kind} of Match {$entrant['match']}", $entrant['match']);
    }

    private function appendPreviewLbRound(array &$preview, int $round, int &$playOrder, array $primary, array $secondary, $byId, ?int $maxPairs = null): array
    {
        $winners = [];
        if ($secondary === []) {
            $pairs = intdiv(count($primary), 2);
            if ($maxPairs !== null) {
                $pairs = min($pairs, $maxPairs);
            }
            for ($i = 0; $i < $pairs; $i++) {
                $order = $this->appendPreviewMatch($preview, -$round, $playOrder, $primary[$i * 2], $primary[$i * 2 + 1], $byId);
                $winners[] = ['match' => $order, 'loser' => false];
            }
            return [$winners, array_slice($primary, $pairs * 2)];
        }

        $pairs = min(count($primary), count($secondary));
        for ($i = 0; $i < $pairs; $i++) {
            $order = $this->appendPreviewMatch($preview, -$round, $playOrder, $primary[$i], $secondary[$i], $byId);
            $winners[] = ['match' => $order, 'loser' => false];
        }
        $carry = count($primary) > $pairs ? array_slice($primary, $pairs) : array_slice($secondary, $pairs);
        return [$winners, $carry];
    }

    /**
     * @var array<int, int> keyed by match id => winner_id, for matches
     *      already resolved (from a prior run) so dependent matches can be
     *      pre-populated instead of waiting for the next regeneration.
     */
    private array $matchWinner = [];

    /**
     * @var array<int, int|null> keyed by match id => loser_id
     */
    private array $matchLoser = [];

    public function initialize(Tournament $tournament)
    {
        $players = $this->getPlayers($tournament);
        $numPlayers = $players->count();
        $splitParticipant = (bool) ($tournament->split_participant ?? false);

        if ($splitParticipant) {
            // The winners bracket gets exactly half of the *next power-of-two
            // bracket size* (not half the raw headcount) - e.g. for 6 players,
            // the next power of two is 8, so winners gets min(6, 4) = 4 real
            // players and losers gets the remaining 2. This guarantees the
            // winners bracket is always a clean power of two internally (no
            // byes anywhere in it).
            $halfBracketSize = $this->halfOfNextPowerOfTwo($numPlayers);
            $topCount = min($numPlayers, $halfBracketSize);
            $topPlayers = $players->take($topCount)->values();
            $bottomPlayers = $players->slice($topCount)->values();
        } else {
            $topPlayers = $players;
            $bottomPlayers = collect();
        }

        $topPlayerCount = $topPlayers->count();
        $totalWbRounds = $this->calculateTotalRound($topPlayerCount);
        $isSplit = $splitParticipant && $bottomPlayers->isNotEmpty();

        $validMatchIds = [];
        $playOrder = 1;

        // ---- Winners bracket -------------------------------------------------
        // Only the top half plays here when split_participant is enabled;
        // otherwise this is everyone, same as before. Round 1 never shows a
        // bye/TBD match - byes always defer to round 2, exactly as in a
        // normal (non-split) bracket. When split, topPlayerCount is always a
        // clean power of two by construction, so firstRoundMatches() produces
        // zero byes here anyway.
        $wbRounds = [];
        for ($i = 1; $i <= $totalWbRounds; $i++) {
            $matchNumber = $i === 1
                ? (int) $this->firstRoundMatches($topPlayerCount)
                : (int) $this->calculateMatchesCountInRound($totalWbRounds, $i);
            $wbRounds[] = $this->filterPlayersByRound($topPlayers, $matchNumber);
        }

        /** @var array<int, TournamentMatch[]> $wbRoundMatches round => ordered matches */
        $wbRoundMatches = [];
        $wbPreReqQueue = collect();

        foreach ($wbRounds as $roundIndex => $roundPlayers) {
            $round = $roundIndex + 1;
            $wbRoundMatches[$round] = [];
            $player1_id = null;
            $player2_id = null;

            foreach ($roundPlayers as $player) {
                if ($player1_id === null) {
                    $player1_id = $player['id'];
                    continue;
                }

                $player2_id = $player['id'];

                $player1PreReq = null;
                $player2PreReq = null;

                if ($player1_id == 0) {
                    $player1PreReq = $wbPreReqQueue->first();
                    $wbPreReqQueue = $wbPreReqQueue->slice(1)->values();
                }
                if ($player2_id == 0) {
                    $player2PreReq = $wbPreReqQueue->first();
                    $wbPreReqQueue = $wbPreReqQueue->slice(1)->values();
                }

                $resolvedP1 = $this->resolvePlayer($player1_id, $player1PreReq, false);
                $resolvedP2 = $this->resolvePlayer($player2_id, $player2PreReq, false);

                $match = TournamentMatch::updateOrCreate([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'suggested_play_order' => $playOrder,
                ], [
                    'player1_id' => $resolvedP1,
                    'player2_id' => $resolvedP2,
                    'player1_prereq_match_id' => $player1PreReq,
                    'player2_prereq_match_id' => $player2PreReq,
                    'player1_is_prereq_match_loser' => false,
                    'player2_is_prereq_match_loser' => false,
                ]);

                $validMatchIds[] = $match->id;
                $this->rememberOutcome($match);
                $wbPreReqQueue->push($match->id);
                $wbRoundMatches[$round][] = $match;

                $playOrder++;
                $player1_id = null;
                $player2_id = null;
            }
        }

        // ---- Losers bracket ----------------------------------------------------
        /** @var array<int, TournamentMatch[]> $lbRoundMatches lbRound => ordered matches */
        $lbRoundMatches = [];

        // Resolved after either branch below: how the LB champion feeds into
        // the grand final - either as a real match's winner, or (only in a
        // rare edge case) as a raw player who advanced on byes alone without
        // ever playing a tracked match.
        $lbChampionPrereqId = null;
        $lbChampionIsLoser = false;
        $lbChampionRawId = null;

        if ($isSplit) {
            $lbRoundCounter = 1;
            $wb1Losers = $wbRoundMatches[1] ?? [];

            [$round1Matches, $carryEntrants] = $this->buildSplitLbRoundOne(
                $tournament,
                $bottomPlayers,
                $lbRoundCounter,
                $playOrder,
                $wb1Losers,
            );
            $lbRoundMatches[$lbRoundCounter] = $round1Matches;
            $validMatchIds = [...$validMatchIds, ...array_map(fn($m) => $m->id, $round1Matches)];

            $survivors = array_map(fn($m) => ['match' => $m->id, 'loser' => false], $round1Matches);
            $carry = $carryEntrants;

            // WB round 1 is already fully accounted for by round 1 above (plus
            // whatever carried over). WB rounds 2..N still need a shrink round
            // (pair survivors together) followed by a merge round (vs that
            // WB round's losers) each.
            for ($k = 2; $k <= $totalWbRounds; $k++) {
                $lbRoundCounter++;
                $population = array_merge($survivors, $carry);
                [$matches, $survivors, $carry] = $this->buildLbRound($tournament, $lbRoundCounter, $playOrder, $population);
                $lbRoundMatches[$lbRoundCounter] = $matches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn($m) => $m->id, $matches)];

                $lbRoundCounter++;
                $wbLosers = array_map(fn($m) => ['match' => $m->id, 'loser' => true], $wbRoundMatches[$k] ?? []);
                $population = array_merge($survivors, $carry);
                [$matches, $survivors, $carry] = $this->buildLbRound($tournament, $lbRoundCounter, $playOrder, $population, $wbLosers);
                $lbRoundMatches[$lbRoundCounter] = $matches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn($m) => $m->id, $matches)];
            }

            // Normally the loop above already leaves exactly one survivor.
            // A skewed split (or WB1 losers that didn't fully fit into round
            // 1's bye slots) can leave more than one - keep shrinking until
            // there's a single LB champion.
            while (count($survivors) + count($carry) > 1) {
                $lbRoundCounter++;
                $population = array_merge($survivors, $carry);
                [$matches, $survivors, $carry] = $this->buildLbRound($tournament, $lbRoundCounter, $playOrder, $population);
                $lbRoundMatches[$lbRoundCounter] = $matches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn($m) => $m->id, $matches)];
            }

            $totalLbRounds = $lbRoundCounter;

            $finalEntrant = $survivors[0] ?? ($carry[0] ?? null);
            if ($finalEntrant !== null) {
                if (array_key_exists('match', $finalEntrant)) {
                    $lbChampionPrereqId = $finalEntrant['match'];
                    $lbChampionIsLoser = $finalEntrant['loser'] ?? false;
                } else {
                    $lbChampionRawId = $finalEntrant['raw'];
                }
            }
        } else {
            // Non-split: seed the losers bracket from WB round 1's losers,
            // then walk each subsequent WB round's losers into the LB.
            //
            // Unlike the old fixed odd/shrink-even/merge cadence, this only
            // inserts a shrink round when the current LB population is
            // actually larger than the incoming WB losers - if the counts
            // already match (common once WB round 1 has byes, e.g. 6
            // players), it merges directly, same as Challonge does. This
            // also fixes a bug in the old code: when population and
            // incoming counts didn't line up 1:1 (which happens whenever
            // WB round 1 has byes), `array_chunk`/naive pairing silently
            // dropped players. buildLbRound's carry mechanism now keeps
            // any unpaired entrant instead of dropping it.
            $lbRoundCounter = 0;
            $population = array_map(
                fn($m) => ['match' => $m->id, 'loser' => true],
                $wbRoundMatches[1] ?? []
            );

            for ($k = 2; $k <= $totalWbRounds; $k++) {
                $incoming = array_map(
                    fn($m) => ['match' => $m->id, 'loser' => true],
                    $wbRoundMatches[$k] ?? []
                );

                if (count($population) > count($incoming)) {
                    $lbRoundCounter++;
                    $diff = count($population) - count($incoming);
                    [$shrinkMatches, $survivors, $carry] = $this->buildLbRound(
                        $tournament,
                        $lbRoundCounter,
                        $playOrder,
                        $population,
                        [],
                        $diff
                    );
                    $lbRoundMatches[$lbRoundCounter] = $shrinkMatches;
                    $validMatchIds = [...$validMatchIds, ...array_map(fn($m) => $m->id, $shrinkMatches)];
                    $population = array_merge($survivors, $carry);
                }

                // Merge current population against this WB round's losers.
                // Reverse the incoming side so nobody immediately re-faces
                // the opponent they just played in WB.
                $lbRoundCounter++;
                $crossedIncoming = array_reverse($incoming);
                [$mergeMatches, $survivors, $carry] = $this->buildLbRound(
                    $tournament,
                    $lbRoundCounter,
                    $playOrder,
                    $population,
                    $crossedIncoming
                );
                $lbRoundMatches[$lbRoundCounter] = $mergeMatches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn($m) => $m->id, $mergeMatches)];
                $population = array_merge($survivors, $carry);
            }

            // Shrink whatever's left down to a single LB champion.
            while (count($population) > 1) {
                $lbRoundCounter++;
                [$matches, $survivors, $carry] = $this->buildLbRound(
                    $tournament,
                    $lbRoundCounter,
                    $playOrder,
                    $population
                );
                $lbRoundMatches[$lbRoundCounter] = $matches;
                $validMatchIds = [...$validMatchIds, ...array_map(fn($m) => $m->id, $matches)];
                $population = array_merge($survivors, $carry);
            }

            $totalLbRounds = $lbRoundCounter;

            $lbChampionPrereqId = null;
            $lbChampionIsLoser = false;
            if (!empty($population)) {
                $final = $population[0];
                $lbChampionPrereqId = $final['match'];
                $lbChampionIsLoser = $final['loser'] ?? false;
            }
        }

        // ---- Grand final ---------------------------------------------------
        $wbChampion = $wbRoundMatches[$totalWbRounds][0] ?? null;

        if ($wbChampion && ($lbChampionPrereqId !== null || $lbChampionRawId !== null)) {
            $gf1 = TournamentMatch::updateOrCreate([
                'tournament_id' => $tournament->id,
                'round' => $totalWbRounds + 1,
                'suggested_play_order' => $playOrder,
            ], [
                'player1_id' => $this->resolvePlayer(null, $wbChampion->id, false),
                'player2_id' => $lbChampionPrereqId !== null
                    ? $this->resolvePlayer(null, $lbChampionPrereqId, $lbChampionIsLoser)
                    : $lbChampionRawId,
                'player1_prereq_match_id' => $wbChampion->id,
                'player2_prereq_match_id' => $lbChampionPrereqId,
                'player1_is_prereq_match_loser' => false,
                'player2_is_prereq_match_loser' => $lbChampionIsLoser,
            ]);
            $validMatchIds[] = $gf1->id;
            $this->rememberOutcome($gf1);
            $playOrder++;

            // Bracket reset: only needed if the LB-side player wins game 1.
            $needsReset = $gf1->winner_id !== null && $gf1->winner_id === $gf1->player2_id;

            if ($needsReset) {
                $gf2 = TournamentMatch::updateOrCreate([
                    'tournament_id' => $tournament->id,
                    'round' => $totalWbRounds + 2,
                    'suggested_play_order' => $playOrder,
                ], [
                    'player1_id' => $gf1->winner_id,
                    'player2_id' => $gf1->loser_id,
                    'player1_prereq_match_id' => $gf1->id,
                    'player2_prereq_match_id' => $gf1->id,
                    'player1_is_prereq_match_loser' => false,
                    'player2_is_prereq_match_loser' => true,
                ]);
                $validMatchIds[] = $gf2->id;
                $playOrder++;
            }
        }

        $tournament->matches()
            ->whereNotIn('id', $validMatchIds)
            ->delete();

        $this->updateMatchesState($tournament, $totalWbRounds, $totalLbRounds);

        return $tournament->matches()
            ->orderBy('suggested_play_order', 'asc')
            ->with(['player1', 'player2', 'matchScores'])
            ->get();
    }

    /**
     * Generic losers-bracket round builder used from LB round 2 onward in
     * split_participant mode. Each entrant is either:
     *   ['raw' => playerId]                   - a raw seed with no match yet
     *   ['match' => matchId, 'loser' => bool]  - the winner/loser of an
     *                                             earlier match
     *
     * With only $primary given, entrants are paired off among themselves (a
     * "shrink" round). With $secondary also given, $primary[i] is paired
     * against $secondary[i] (a "merge" round, e.g. LB survivors vs WB
     * drop-ins). Whichever side has leftover entrants that couldn't be
     * paired this round is returned as $carry rather than being dropped -
     * the caller folds $carry into the next round's population.
     *
     * @return array{0: TournamentMatch[], 1: array, 2: array} [matches, winnerEntrants, carryEntrants]
     */
    private function buildLbRound(Tournament $tournament, int $round, int &$playOrder, array $primary, array $secondary = [], ?int $maxPairs = null): array
    {
        $matches = [];
        $winners = [];
        $carry = [];

        if (empty($secondary)) {
            $count = count($primary);
            $pairs = intdiv($count, 2);
            if ($maxPairs !== null) {
                // Cap how far this "shrink" round reduces the population -
                // e.g. shrink just enough to match an upcoming WB-losers
                // batch size, rather than always pairing everyone off.
                $pairs = min($pairs, $maxPairs);
            }

            for ($i = 0; $i < $pairs; $i++) {
                $match = $this->createEntrantMatch($tournament, $round, $playOrder, $primary[$i * 2], $primary[$i * 2 + 1]);
                $playOrder++;
                $matches[] = $match;
                $winners[] = ['match' => $match->id, 'loser' => false];
            }

            // Anything past the paired prefix carries forward untouched -
            // this can be more than one entrant when $maxPairs caps the
            // shrink below a full pairing.
            for ($i = $pairs * 2; $i < $count; $i++) {
                $carry[] = $primary[$i];
            }
        } else {
            $pairCount = min(count($primary), count($secondary));
            for ($i = 0; $i < $pairCount; $i++) {
                $match = $this->createEntrantMatch($tournament, $round, $playOrder, $primary[$i], $secondary[$i]);
                $playOrder++;
                $matches[] = $match;
                $winners[] = ['match' => $match->id, 'loser' => false];
            }
            if (count($primary) > $pairCount) {
                $carry = array_slice($primary, $pairCount);
            } elseif (count($secondary) > $pairCount) {
                $carry = array_slice($secondary, $pairCount);
            }
        }

        return [$matches, $winners, $carry];
    }

    private function createEntrantMatch(Tournament $tournament, int $round, int $playOrder, array $slot1, array $slot2): TournamentMatch
    {
        $prereq1 = $slot1['match'] ?? null;
        $prereq2 = $slot2['match'] ?? null;
        $isLoser1 = $slot1['loser'] ?? false;
        $isLoser2 = $slot2['loser'] ?? false;

        $match = TournamentMatch::updateOrCreate([
            'tournament_id' => $tournament->id,
            'round' => -$round,
            'suggested_play_order' => $playOrder,
        ], [
            'player1_id' => $this->resolveEntrantPlayer($slot1),
            'player2_id' => $this->resolveEntrantPlayer($slot2),
            'player1_prereq_match_id' => $prereq1,
            'player2_prereq_match_id' => $prereq2,
            'player1_is_prereq_match_loser' => $isLoser1,
            'player2_is_prereq_match_loser' => $isLoser2,
        ]);

        $this->rememberOutcome($match);

        return $match;
    }

    /**
     * @param array{raw?: int, match?: int, loser?: bool} $entrant
     */
    private function resolveEntrantPlayer(array $entrant): ?int
    {
        if (array_key_exists('raw', $entrant)) {
            return $this->resolvePlayer($entrant['raw'], null, false);
        }

        return $this->resolvePlayer(null, $entrant['match'], $entrant['loser'] ?? false);
    }

    /**
     * Resolve an actual player id for a slot, given either a raw seeded id
     * (0 == bye, used for WB round 1) or a prereq match id to pull a
     * winner/loser from once that match has been decided.
     */
    private function resolvePlayer(?int $rawId, ?int $prereqMatchId, bool $isLoserPrereq): ?int
    {
        if ($prereqMatchId === null) {
            return ($rawId === null || $rawId === 0) ? null : $rawId;
        }

        if ($isLoserPrereq) {
            return $this->matchLoser[$prereqMatchId] ?? null;
        }

        return $this->matchWinner[$prereqMatchId] ?? null;
    }

    private function rememberOutcome(TournamentMatch $match): void
    {
        if ($match->winner_id === null) {
            return;
        }

        $this->matchWinner[$match->id] = $match->winner_id;
        $this->matchLoser[$match->id] = $match->loser_id
            ?? ($match->player1_id === $match->winner_id ? $match->player2_id : $match->player1_id);
    }

    private function updateMatchesState(Tournament $tournament, int $totalWbRounds, int $totalLbRounds): void
    {
        $tournament->matches()
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->update(['state' => TournamentMatchStateEnum::OPEN]);

        $tournament->matches()
            ->whereNotNull('winner_id')
            ->update(['state' => TournamentMatchStateEnum::COMPLETE]);

        $grandFinals = $tournament->matches()
            ->where('round', '>', $totalWbRounds)
            ->orderByDesc('round')
            ->get();

        $decisiveFinal = $grandFinals->first(fn($m) => $m->winner_id !== null);
        $allOthersDone = !$tournament->matches()
            ->where('round', '<=', $totalWbRounds)
            ->whereNull('winner_id')
            ->exists();

        if ($allOthersDone && $decisiveFinal) {
            $this->assignFinalRanks($tournament, $totalWbRounds, $totalLbRounds);
        } else {
            $tournament->players()->update(['final_rank' => null]);
        }
    }

    private function assignFinalRanks(Tournament $tournament, int $totalWbRounds, int $totalLbRounds): void
    {
        $grandFinal = $tournament->matches()
            ->where('round', '>', $totalWbRounds)
            ->orderByDesc('round')
            ->whereNotNull('winner_id')
            ->first();

        if (!$grandFinal) {
            return;
        }

        $tournament->players()->find($grandFinal->winner_id)?->update(['final_rank' => 1]);
        $tournament->players()->find($grandFinal->loser_id)?->update(['final_rank' => 2]);

        $rank = 3;
        for ($round = $totalLbRounds; $round >= 1; $round--) {
            $losers = $tournament->matches()
                ->where('round', -$round)
                ->whereNotNull('loser_id')
                ->pluck('loser_id');

            if ($losers->isEmpty()) {
                continue;
            }

            foreach ($losers as $loserId) {
                $tournament->players()->find($loserId)?->update(['final_rank' => $rank]);
            }

            $rank += $losers->count();
        }
    }

    private function filterPlayersByRound(&$players, int $matchNumber)
    {
        $num = $matchNumber * 2;
        $data = $players->take($num);
        $players = collect($players->slice($num))->values();

        $missing = $num - $data->count();

        if ($missing > 0) {
            $data = $data->values();

            for ($i = 0; $i < $missing; $i++) {
                if ($i % 2 === 0) {
                    $position = $i;
                } else {
                    $position = $data->count() - $i + 1;
                    if ($position < 0) {
                        $position = $data->count();
                    }
                }

                $data->splice($position, 0, [['id' => 0]]);
            }
        }

        return $data;
    }

    private function firstRoundMatches(int $numPlayers)
    {
        if ($numPlayers < 2) {
            return 0;
        }

        $nextPowerOfTwo = pow(2, ceil(log($numPlayers, 2)));
        $byes = $nextPowerOfTwo - $numPlayers;

        return ($numPlayers - $byes) / 2;
    }
}

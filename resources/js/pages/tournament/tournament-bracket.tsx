import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface TournamentBracketPlayer {
    id: number;
    name: string;
}

export interface TournamentBracketMatch {
    id: number;
    state: string | null;
    is_tie?: boolean | null;
    round: number | null;
    suggested_play_order: number | null;
    player1_id: number | null;
    player2_id: number | null;
    player1_prereq_match_id: number | null;
    player2_prereq_match_id: number | null;
    player1_is_prereq_match_loser: boolean;
    player2_is_prereq_match_loser: boolean;
    player1_score: string | null;
    player2_score: string | null;
    winner_id: number | null;
}

interface TournamentBracketProps {
    modeType: string | null;
    players: TournamentBracketPlayer[];
    matches: TournamentBracketMatch[];
    onAddScore: (match: TournamentBracketMatch) => void;
}

interface BracketRound {
    round: number;
    matches: TournamentBracketMatch[];
}

function toDisplayLabel(value: string | null) {
    if (!value) {
        return 'N/A';
    }

    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function sortMatches(matches: TournamentBracketMatch[]) {
    return [...matches].sort((left, right) => {
        const leftOrder = left.suggested_play_order ?? Number.MAX_SAFE_INTEGER;
        const rightOrder = right.suggested_play_order ?? Number.MAX_SAFE_INTEGER;

        if (leftOrder !== rightOrder) {
            return leftOrder - rightOrder;
        }

        return left.id - right.id;
    });
}

function roundLabelForUpper(roundPosition: number, totalRounds: number) {
    if (roundPosition === totalRounds) {
        return 'Final';
    }

    if (roundPosition === totalRounds - 1) {
        return 'Semifinal';
    }

    if (roundPosition === totalRounds - 2) {
        return 'Quarterfinal';
    }

    return `Round ${roundPosition}`;
}

function roundLabelForLower(round: number) {
    return `Lower Round ${Math.abs(round)}`;
}

function getPlayerLabel(
    playerById: Record<number, TournamentBracketPlayer>,
    playerId: number | null,
    prereqMatchId: number | null,
    isLoserSlot: boolean,
) {
    if (playerId) {
        return playerById[playerId]?.name ?? `Player #${playerId}`;
    }

    if (prereqMatchId) {
        if (isLoserSlot) {
            return `Loser of Match #${prereqMatchId}`;
        }

        return `Winner of Match #${prereqMatchId}`;
    }

    return 'TBD';
}

function BracketColumns({
    rounds,
    playerById,
    isLowerBracket,
    onAddScore,
}: {
    rounds: BracketRound[];
    playerById: Record<number, TournamentBracketPlayer>;
    isLowerBracket: boolean;
    onAddScore: (match: TournamentBracketMatch) => void;
}) {
    if (rounds.length === 0) {
        return <p className="text-muted-foreground text-sm">No matches in this bracket yet.</p>;
    }

    return (
        <div className="overflow-x-auto pb-1">
            <div className="flex min-w-max items-start gap-5">
                {rounds.map((roundData, roundIndex) => {
                    const label = isLowerBracket
                        ? roundLabelForLower(roundData.round)
                        : roundLabelForUpper(roundIndex + 1, rounds.length);

                    return (
                        <div key={roundData.round} className="flex min-w-64 flex-col gap-2">
                            <div className="space-y-1 px-1">
                                <p className="text-sm font-medium">{label}</p>
                                <p className="text-muted-foreground text-xs">{roundData.matches.length} match(es)</p>
                            </div>

                            <div className="space-y-3">
                                {roundData.matches.map((match) => {
                                    const player1IsWinner = match.player1_id !== null && match.winner_id === match.player1_id;
                                    const player2IsWinner = match.player2_id !== null && match.winner_id === match.player2_id;
                                    const isTie = match.is_tie === true;

                                    return (
                                        <div key={match.id} className="bg-background rounded-md border p-3 shadow-sm">
                                            <div className="mb-2 flex items-center justify-between">
                                                <p className="text-xs font-medium">Match #{match.id}</p>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {toDisplayLabel(match.state)}
                                                    </Badge>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-6 px-2 text-[10px]"
                                                        onClick={() => onAddScore(match)}
                                                        disabled={match.player1_id === null || match.player2_id === null}
                                                        type="button"
                                                    >
                                                        Add score
                                                    </Button>
                                                </div>
                                            </div>

                                            <div className="space-y-1.5">
                                                <div className="flex items-center justify-between rounded border px-2 py-1 text-xs">
                                                    <span className={player1IsWinner ? 'font-semibold text-blue-400' : ''}>
                                                        {getPlayerLabel(
                                                            playerById,
                                                            match.player1_id,
                                                            match.player1_prereq_match_id,
                                                            match.player1_is_prereq_match_loser,
                                                        )}
                                                    </span>
                                                    <span className="text-muted-foreground">{match.player1_score ?? '-'}</span>
                                                </div>
                                                <div className="flex items-center justify-between rounded border px-2 py-1 text-xs">
                                                    <span className={player2IsWinner ? 'font-semibold text-blue-400' : ''}>
                                                        {getPlayerLabel(
                                                            playerById,
                                                            match.player2_id,
                                                            match.player2_prereq_match_id,
                                                            match.player2_is_prereq_match_loser,
                                                        )}
                                                    </span>
                                                    <span className="text-muted-foreground">{match.player2_score ?? '-'}</span>
                                                </div>
                                            </div>

                                            {isTie ? (
                                                <p className="text-muted-foreground mt-2 text-[11px]">Result: Tie</p>
                                            ) : match.winner_id ? (
                                                <p className="text-muted-foreground mt-2 text-[11px]">
                                                    Winner: {playerById[match.winner_id]?.name ?? `Player #${match.winner_id}`}
                                                </p>
                                            ) : null}
                                        </div>
                                    );
                                })}
                            </div>

                            {roundIndex < rounds.length - 1 ? <div className="text-muted-foreground px-1 text-xs">→ Next round</div> : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export function TournamentBracket({ modeType, players, matches, onAddScore }: TournamentBracketProps) {
    const playerById = players.reduce<Record<number, TournamentBracketPlayer>>((accumulator, player) => {
        accumulator[player.id] = player;
        return accumulator;
    }, {});

    const validMatches = matches.filter((match) => typeof match.round === 'number' && match.round !== 0) as Array<
        TournamentBracketMatch & { round: number }
    >;

    const upperRounds = Array.from(new Set(validMatches.filter((match) => match.round > 0).map((match) => match.round)))
        .sort((left, right) => left - right)
        .map((round) => ({
            round,
            matches: sortMatches(validMatches.filter((match) => match.round === round)),
        }));

    const lowerRounds = Array.from(new Set(validMatches.filter((match) => match.round < 0).map((match) => match.round)))
        .sort((left, right) => Math.abs(left) - Math.abs(right))
        .map((round) => ({
            round,
            matches: sortMatches(validMatches.filter((match) => match.round === round)),
        }));

    const isDoubleElimination = modeType === 'double_elimination';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Bracket</CardTitle>
                <CardDescription>
                    {isDoubleElimination
                        ? 'Upper and lower bracket progression for double elimination.'
                        : 'Round-by-round tournament progression.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {matches.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No matches generated yet.</p>
                ) : (
                    <>
                        <div className="space-y-2">
                            <p className="text-sm font-semibold">Upper Bracket</p>
                            <BracketColumns rounds={upperRounds} playerById={playerById} isLowerBracket={false} onAddScore={onAddScore} />
                        </div>

                        {isDoubleElimination ? (
                            <div className="space-y-2 border-t pt-4">
                                <p className="text-sm font-semibold">Lower Bracket</p>
                                <BracketColumns rounds={lowerRounds} playerById={playerById} isLowerBracket={true} onAddScore={onAddScore} />
                            </div>
                        ) : null}
                    </>
                )}
            </CardContent>
        </Card>
    );
}

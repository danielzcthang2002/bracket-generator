import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useLayoutEffect, useRef, useState } from 'react';

interface TournamentBracketPlayer {
    id: number;
    name: string;
}

interface TournamentBracketParticipantPlayer {
    id: number;
    name: string;
}

interface TournamentBracketParticipant {
    id: number;
    player_id: number | null;
    position: number | null;
    score: string | null;
    rank: number | null;
    is_winner: boolean;
    player: TournamentBracketParticipantPlayer | null;
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
    participants?: TournamentBracketParticipant[];
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

interface ConnectorPath {
    id: string;
    path: string;
}

export interface BracketConnection {
    from: number;
    to: number;
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

function matchStateClass(state: string | null) {
    switch (state) {
        case 'open':
            return 'border-lime-300/30 bg-lime-300/15 text-lime-300';
        case 'complete':
            return 'border-emerald-300/30 bg-emerald-300/15 text-emerald-300';
        case 'close':
            return 'border-white/15 bg-white/10 text-white/60';
        case 'pending':
        default:
            return 'border-amber-300/30 bg-amber-300/15 text-amber-200';
    }
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

function getParticipantLabel(participant: TournamentBracketParticipant) {
    if (participant.player) {
        return participant.player.name;
    }

    if (participant.player_id) {
        return `Player #${participant.player_id}`;
    }

    return 'TBD';
}

export function BracketConnectors({ container, connections }: { container: HTMLDivElement | null; connections: BracketConnection[] }) {
    const [paths, setPaths] = useState<ConnectorPath[]>([]);
    const [size, setSize] = useState({ width: 0, height: 0 });

    useLayoutEffect(() => {
        if (!container) return;

        const draw = () => {
            const containerRect = container.getBoundingClientRect();
            const matchElements = new Map<number, HTMLElement>();

            container.querySelectorAll<HTMLElement>('[data-bracket-node-id]').forEach((element) => {
                matchElements.set(Number(element.dataset.bracketNodeId), element);
            });

            const nextPaths: ConnectorPath[] = [];

            connections.forEach((connection, connectionIndex) => {
                const target = matchElements.get(connection.to);
                const source = matchElements.get(connection.from);
                if (!target || !source) return;

                const sourceRect = source.getBoundingClientRect();
                const targetRect = target.getBoundingClientRect();
                const startX = sourceRect.right - containerRect.left;
                const startY = sourceRect.top + sourceRect.height / 2 - containerRect.top;
                const endX = targetRect.left - containerRect.left;
                const endY = targetRect.top + targetRect.height / 2 - containerRect.top;
                const middleX = startX + (endX - startX) / 2;

                nextPaths.push({
                    id: `${connection.from}-${connection.to}-${connectionIndex}`,
                    path: `M ${startX} ${startY} H ${middleX} V ${endY} H ${endX}`,
                });
            });

            setSize({ width: container.scrollWidth, height: container.scrollHeight });
            setPaths(nextPaths);
        };

        draw();
        const observer = new ResizeObserver(draw);
        observer.observe(container);
        container.querySelectorAll<HTMLElement>('[data-bracket-node-id]').forEach((element) => observer.observe(element));

        return () => observer.disconnect();
    }, [connections, container]);

    return (
        <svg
            aria-hidden="true"
            className="pointer-events-none absolute top-0 left-0 z-0 overflow-visible"
            width={size.width}
            height={size.height}
            viewBox={`0 0 ${size.width} ${size.height}`}
        >
            {paths.map((connector) => (
                <path key={connector.id} d={connector.path} fill="none" className="stroke-lime-300/55" strokeWidth="1.5" strokeLinejoin="round" />
            ))}
        </svg>
    );
}

function BracketColumns({
    rounds,
    playerById,
    isLowerBracket,
    modeType,
    onAddScore,
}: {
    rounds: BracketRound[];
    playerById: Record<number, TournamentBracketPlayer>;
    isLowerBracket: boolean;
    modeType: string | null;
    onAddScore: (match: TournamentBracketMatch) => void;
}) {
    const canvasRef = useRef<HTMLDivElement>(null);
    const [canvas, setCanvas] = useState<HTMLDivElement | null>(null);

    useLayoutEffect(() => setCanvas(canvasRef.current), []);

    const connections = rounds.flatMap((round) =>
        round.matches.flatMap((match) =>
            [match.player1_prereq_match_id, match.player2_prereq_match_id]
                .filter((sourceId): sourceId is number => sourceId !== null)
                .map((sourceId) => ({ from: sourceId, to: match.id })),
        ),
    );

    if (rounds.length === 0) {
        return <p className="text-muted-foreground text-sm">No matches in this bracket yet.</p>;
    }

    return (
        <div className="overflow-x-auto pb-1">
            <div ref={canvasRef} className="relative flex min-w-max items-stretch gap-14 py-1">
                <BracketConnectors container={canvas} connections={connections} />
                {rounds.map((roundData, roundIndex) => {
                    const label = isLowerBracket ? roundLabelForLower(roundData.round) : roundLabelForUpper(roundIndex + 1, rounds.length);

                    return (
                        <div key={roundData.round} className="relative z-10 flex min-w-64 flex-col">
                            <div className="space-y-1 px-1">
                                <p className="text-sm font-medium">{label}</p>
                                <p className="text-muted-foreground text-xs">{roundData.matches.length} match(es)</p>
                            </div>

                            <div className="flex flex-1 flex-col justify-around gap-6 py-3">
                                {roundData.matches.map((match) => {
                                    const player1IsWinner = match.player1_id !== null && match.winner_id === match.player1_id;
                                    const player2IsWinner = match.player2_id !== null && match.winner_id === match.player2_id;
                                    const isTie = match.is_tie === true;
                                    const participants = [...(match.participants ?? [])].sort((left, right) => {
                                        const leftRank = left.rank ?? Number.MAX_SAFE_INTEGER;
                                        const rightRank = right.rank ?? Number.MAX_SAFE_INTEGER;

                                        if (leftRank !== rightRank) {
                                            return leftRank - rightRank;
                                        }

                                        const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
                                        const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

                                        if (leftPosition !== rightPosition) {
                                            return leftPosition - rightPosition;
                                        }

                                        return left.id - right.id;
                                    });
                                    const showParticipants = participants.length > 0;
                                    const isFreeForAll = modeType === 'free_for_all';
                                    const canAddScore = isFreeForAll
                                        ? participants.length > 0
                                        : match.player1_id !== null && match.player2_id !== null;

                                    return (
                                        <div
                                            key={match.id}
                                            data-bracket-node-id={match.id}
                                            className="rounded-xl border border-white/10 bg-[#1b2119] p-3 text-white shadow-lg shadow-black/20"
                                        >
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="text-xs text-red-400">{match.suggested_play_order}</span>
                                                <p className="text-xs font-medium">Match #{match.id}</p>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="outline" className={`text-[10px] ${matchStateClass(match.state)}`}>
                                                        {toDisplayLabel(match.state)}
                                                    </Badge>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-6 rounded-full border-lime-300/30 bg-lime-300/10 px-2 text-[10px] font-semibold text-lime-300 hover:bg-lime-300 hover:text-[#10140c]"
                                                        onClick={() => onAddScore(match)}
                                                        disabled={!canAddScore}
                                                        type="button"
                                                    >
                                                        Add score
                                                    </Button>
                                                </div>
                                            </div>

                                            {showParticipants ? (
                                                <div className="space-y-1.5">
                                                    {participants.map((participant) => (
                                                        <div
                                                            key={participant.id}
                                                            className="flex items-center justify-between rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-xs"
                                                        >
                                                            <span className={participant.is_winner ? 'font-semibold text-lime-300' : ''}>
                                                                <span className="text-muted-foreground mr-2">
                                                                    {participant.rank !== null ? `#${participant.rank}` : 'TBD'}
                                                                </span>
                                                                {getParticipantLabel(participant)}
                                                            </span>
                                                            <span className="text-muted-foreground">{participant.score ?? '-'}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div className="space-y-1.5">
                                                    <div className="flex items-center justify-between rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-xs">
                                                        <span className={player1IsWinner ? 'font-semibold text-lime-300' : ''}>
                                                            {getPlayerLabel(
                                                                playerById,
                                                                match.player1_id,
                                                                match.player1_prereq_match_id,
                                                                match.player1_is_prereq_match_loser,
                                                            )}
                                                        </span>
                                                        <span className="text-muted-foreground">{match.player1_score ?? '-'}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-xs">
                                                        <span className={player2IsWinner ? 'font-semibold text-lime-300' : ''}>
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
                                            )}

                                            {showParticipants ? (
                                                <p className="text-muted-foreground mt-2 text-[11px]">Participants: {participants.length}</p>
                                            ) : isTie ? (
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
        <Card className="overflow-hidden rounded-2xl border-black/8 bg-[#11150f] text-white shadow-none dark:border-white/8">
            <CardHeader>
                <CardTitle>Bracket</CardTitle>
                <CardDescription>
                    {isDoubleElimination ? 'Upper and lower bracket progression for double elimination.' : 'Round-by-round tournament progression.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {matches.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No matches generated yet.</p>
                ) : (
                    <>
                        <div className="space-y-2">
                            <p className="text-sm font-semibold">Upper Bracket</p>
                            <BracketColumns
                                rounds={upperRounds}
                                playerById={playerById}
                                isLowerBracket={false}
                                modeType={modeType}
                                onAddScore={onAddScore}
                            />
                        </div>

                        {isDoubleElimination ? (
                            <div className="space-y-2 border-t pt-4">
                                <p className="text-sm font-semibold">Lower Bracket</p>
                                <BracketColumns
                                    rounds={lowerRounds}
                                    playerById={playerById}
                                    isLowerBracket={true}
                                    modeType={modeType}
                                    onAddScore={onAddScore}
                                />
                            </div>
                        ) : null}
                    </>
                )}
            </CardContent>
        </Card>
    );
}

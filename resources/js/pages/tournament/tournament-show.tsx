import InputError from '@/components/input-error';
import { TournamentStatusBadge } from '@/components/tournament-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { MatchScoreModal } from '@/pages/tournament/match-score-modal';
import { BulkAddPlayerModal, EditPlayerModal } from '@/pages/tournament/player-modals';
import { BracketConnectors, TournamentBracket, type BracketConnection, type TournamentBracketMatch } from '@/pages/tournament/tournament-bracket';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDownIcon, GripVertical, Medal, Trash2, Trophy } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';

interface TournamentPlayer {
    id: number;
    name: string;
    checked_in: boolean;
    seed: number | null;
    final_rank: number | null;
}

interface TournamentMatch {
    id: number;
    state: string | null;
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
    participants?: TournamentMatchParticipant[];
}

interface TournamentMatchParticipantPlayer {
    id: number;
    name: string;
}

interface TournamentMatchParticipant {
    id: number;
    player_id: number | null;
    position: number | null;
    score: string | null;
    rank: number | null;
    is_winner: boolean;
    player: TournamentMatchParticipantPlayer | null;
}

interface TournamentDetail {
    id: number;
    name: string;
    open_id: string;
    description: string | null;
    mode_type: string | null;
    status: string | null;
    start_at: string | null;
    end_at: string | null;
    require_check_in: boolean;
    check_in_time: string | null;
    max_entry: number | null;
    split_participant: boolean;
    ffa_heat_size: number | null;
    ffa_advance_count: number | null;
    head_to_head_count: number | null;
    rank_by: string | null;
    points_per_match_win: string | null;
    points_per_match_tie: string | null;
    points_per_set_win: string | null;
    points_per_set_tie: string | null;
    points_per_bye: string | null;
    swiss_rounds: number | null;
    players_count: number;
    matches_count: number;
    players: TournamentPlayer[];
    matches: TournamentMatch[];
}

interface TournamentShowProps {
    tournament: TournamentDetail;
    bracketPreview: Record<string, PreviewMatch[]> | PreviewMatch[][];
}

interface PreviewSlot {
    type: 'player' | 'tbd';
    id?: number;
    name?: string | null;
    seed?: number | null;
    label?: string;
    prereq_suggested_play_order?: number | null;
}

interface PreviewMatch {
    round: number;
    suggested_play_order: number;
    player1?: PreviewSlot;
    player2?: PreviewSlot;
    participants?: PreviewSlot[];
}

function previewSlotLabel(slot: PreviewSlot) {
    return slot.type === 'player' ? (slot.name ?? `Player #${slot.id}`) : (slot.label ?? 'TBD');
}

function PreviewPlayerSlot({ slot, disabled, onSwapSeeds }: { slot?: PreviewSlot; disabled: boolean; onSwapSeeds: (firstId: number, secondId: number) => void }) {
    const [isDragTarget, setIsDragTarget] = useState(false);
    const playerId = slot?.type === 'player' && typeof slot.id === 'number' ? slot.id : null;
    const isPlayer = playerId !== null;

    return (
        <p
            draggable={isPlayer && !disabled}
            onDragStart={(event) => {
                if (!isPlayer) return;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(playerId));
            }}
            onDragOver={(event) => {
                if (!isPlayer || disabled) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                setIsDragTarget(true);
            }}
            onDragLeave={() => setIsDragTarget(false)}
            onDrop={(event) => {
                event.preventDefault();
                setIsDragTarget(false);
                if (!isPlayer || disabled) return;

                const draggedPlayerId = Number(event.dataTransfer.getData('text/plain'));
                if (playerId !== null && Number.isInteger(draggedPlayerId) && draggedPlayerId !== playerId) {
                    onSwapSeeds(draggedPlayerId, playerId);
                }
            }}
            onDragEnd={() => setIsDragTarget(false)}
            title={isPlayer ? 'Drag onto another player to swap seeds' : undefined}
            className={`flex items-center gap-2 rounded-lg border px-2 py-1 transition ${
                isDragTarget ? 'border-lime-300 bg-lime-300/20' : 'border-white/10 bg-white/5'
            } ${isPlayer && !disabled ? 'cursor-grab active:cursor-grabbing' : ''}`}
        >
            {isPlayer && <GripVertical className="size-3.5 shrink-0 text-white/35" />}
            <span className="truncate">{slot ? previewSlotLabel(slot) : 'TBD'}</span>
            {isPlayer && slot?.seed != null && <span className="ml-auto text-xs text-white/40">#{slot.seed}</span>}
        </p>
    );
}

function PreviewBracketColumns({
    rounds,
    swappingSeeds,
    onSwapSeeds,
}: {
    rounds: Array<{ round: number; matches: PreviewMatch[] }>;
    swappingSeeds: boolean;
    onSwapSeeds: (firstId: number, secondId: number) => void;
}) {
    const canvasRef = useRef<HTMLDivElement>(null);
    const [canvas, setCanvas] = useState<HTMLDivElement | null>(null);

    useLayoutEffect(() => setCanvas(canvasRef.current), []);

    const connections: BracketConnection[] = rounds.flatMap(({ matches }) =>
        matches.flatMap((match) =>
            [match.player1, match.player2]
                .map((slot) => slot?.prereq_suggested_play_order)
                .filter((source): source is number => typeof source === 'number')
                .map((source) => ({ from: source, to: match.suggested_play_order })),
        ),
    );

    return (
        <div className="overflow-x-auto pb-1">
            <div ref={canvasRef} className="relative flex min-w-max items-stretch gap-14 py-1">
                <BracketConnectors container={canvas} connections={connections} />
                {rounds.map(({ round, matches }) => (
                    <div key={round} className="relative z-10 flex w-64 flex-col">
                        <p className="px-1 text-sm font-medium">{round < 0 ? `Lower Round ${Math.abs(round)}` : `Round ${round}`}</p>
                        <div className="flex flex-1 flex-col justify-around gap-6 py-3">
                            {matches.map((match) => (
                                <div
                                    key={match.suggested_play_order}
                                    data-bracket-node-id={match.suggested_play_order}
                                    className="space-y-2 rounded-xl border border-white/10 bg-[#1b2119] p-3 text-sm text-white shadow-lg shadow-black/20"
                                >
                                    <p className="text-xs text-white/40">Match {match.suggested_play_order}</p>
                                    {match.participants ? (
                                        match.participants.map((slot, index) => (
                                            <PreviewPlayerSlot
                                                key={slot.id ?? index}
                                                slot={slot}
                                                disabled={swappingSeeds}
                                                onSwapSeeds={onSwapSeeds}
                                            />
                                        ))
                                    ) : (
                                        <>
                                            <PreviewPlayerSlot slot={match.player1} disabled={swappingSeeds} onSwapSeeds={onSwapSeeds} />
                                            <PreviewPlayerSlot slot={match.player2} disabled={swappingSeeds} onSwapSeeds={onSwapSeeds} />
                                        </>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
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

function toDisplayDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
}

export default function TournamentShow({ tournament, bracketPreview }: TournamentShowProps) {
    const [openBulkAddPlayerModal, setOpenBulkAddPlayerModal] = useState(false);
    const [editingPlayer, setEditingPlayer] = useState<TournamentPlayer | null>(null);
    const [deletingPlayer, setDeletingPlayer] = useState<TournamentPlayer | null>(null);
    const [confirmingTournamentDelete, setConfirmingTournamentDelete] = useState(false);
    const [confirmingBulkDelete, setConfirmingBulkDelete] = useState(false);
    const [playersOpen, setPlayersOpen] = useState(false);
    const [scoringMatch, setScoringMatch] = useState<TournamentMatch | null>(null);
    const [swappingSeeds, setSwappingSeeds] = useState(false);
    const { delete: destroy, processing: deleteProcessing } = useForm({});
    const bulkDeleteForm = useForm<{ player_ids: number[] }>({ player_ids: [] });
    const { post: postStartTournament, processing: startTournamentProcessing } = useForm();
    const { post: postEndTournament, processing: endTournamentProcessing } = useForm();
    const isDoubleElimination = tournament.mode_type === 'double_elimination';
    const isFreeForAll = tournament.mode_type === 'free_for_all';
    const isRoundRobin = tournament.mode_type === 'round_robin';
    const isSwiss = tournament.mode_type === 'swiss';
    const minimumSwissRounds = Math.max(1, ...tournament.matches.map((match) => match.round ?? 0));
    const swissRoundsForm = useForm({
        swiss_rounds: String(
            tournament.swiss_rounds ?? Math.max(1, Math.ceil(Math.log2(tournament.players.filter((player) => player.checked_in).length || 1))),
        ),
    });
    const canDeletePlayers = tournament.status !== 'started' && tournament.status !== 'ended';
    const finalRankings = [...tournament.players]
        .filter((player) => player.final_rank !== null)
        .sort((left, right) => (left.final_rank ?? Number.MAX_SAFE_INTEGER) - (right.final_rank ?? Number.MAX_SAFE_INTEGER));
    const showRankAndPoints = isRoundRobin || isSwiss;
    const previewRounds = Object.entries(bracketPreview ?? {})
        .map(([round, matches]) => ({ round: Number(round), matches }))
        .filter(({ matches }) => matches.length > 0);
    const upperPreviewRounds = previewRounds.filter(({ round }) => round > 0).sort((a, b) => a.round - b.round);
    const lowerPreviewRounds = previewRounds.filter(({ round }) => round < 0).sort((a, b) => Math.abs(a.round) - Math.abs(b.round));
    const previewNote = isSwiss
        ? 'Only round 1 can be predicted. Later pairings depend on results and standings.'
        : isFreeForAll
          ? 'Only round 1 can be predicted. Later heats depend on finishing positions.'
          : isDoubleElimination
            ? 'A bracket reset final is added only if the lower bracket finalist wins the first final.'
            : 'Based on the currently checked-in players in seed order.';

    const swapPlayerSeeds = (firstPlayerId: number, secondPlayerId: number) => {
        if (swappingSeeds) return;

        router.put(
            route('tournament.player.seeds.swap', tournament.open_id),
            { first_player_id: firstPlayerId, second_player_id: secondPlayerId },
            {
                preserveScroll: true,
                onStart: () => setSwappingSeeds(true),
                onFinish: () => setSwappingSeeds(false),
            },
        );
    };

    const submitDeletePlayer = () => {
        if (!deletingPlayer) {
            return;
        }

        destroy(route('tournament.player.destroy', [tournament.open_id, deletingPlayer.id]), {
            preserveScroll: true,
            onSuccess: () => setDeletingPlayer(null),
        });
    };

    const submitDeleteTournament = () => {
        if (deleteProcessing) {
            return;
        }

        destroy(route('tournament.destroy', [tournament.open_id]), {
            preserveScroll: true,
            onSuccess: () => setConfirmingTournamentDelete(false),
        });
    };

    const togglePlayerSelection = (playerId: number, selected: boolean) => {
        bulkDeleteForm.setData(
            'player_ids',
            selected ? [...bulkDeleteForm.data.player_ids, playerId] : bulkDeleteForm.data.player_ids.filter((selectedId) => selectedId !== playerId),
        );
    };

    const submitBulkDelete = () => {
        bulkDeleteForm.delete(route('tournament.player.bulk.destroy', tournament.open_id), {
            preserveScroll: true,
            onSuccess: () => {
                bulkDeleteForm.reset();
                setConfirmingBulkDelete(false);
            },
        });
    };

    const startTournament = () => {
        if (startTournamentProcessing) {
            return;
        }
        postStartTournament(route('tournament.start', [tournament.open_id]), {
            preserveScroll: true,
        });
    };

    const endTournament = () => {
        if (endTournamentProcessing) return;

        postEndTournament(route('tournament.end', [tournament.open_id]));
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Tournament',
            href: '/tournaments',
        },
        {
            title: tournament.name,
            href: `/tournaments/${tournament.open_id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${tournament.name} | Tournament`} />

            <div className="flex h-full flex-1 flex-col gap-5 bg-[#f4f6ed] p-4 md:p-6 dark:bg-[#0b0e09]">
                <div className="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-[#172012] p-6 text-white shadow-xl shadow-black/10 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div className="absolute -top-20 right-10 size-56 rounded-full bg-lime-400/15 blur-3xl" />
                    <div className="space-y-1">
                        <p className="text-sm font-semibold text-lime-300">{toDisplayLabel(tournament.mode_type)}</p>
                        <h1 className="text-3xl font-bold tracking-[-0.04em]">{tournament.name}</h1>
                        <p className="text-sm text-white/40">Tournament ID: {tournament.open_id}</p>
                    </div>
                    <div className="relative flex flex-wrap items-center gap-2">
                        {/* <AddPlayerModal open={openAddPlayerModal} onOpenChange={setOpenAddPlayerModal} tournamentOpenId={tournament.open_id} /> */}
                        {tournament.status == 'pending' && (
                            <Button onClick={startTournament} className="rounded-full bg-lime-400 font-semibold text-[#10140c] hover:bg-lime-300">
                                Start tournament
                            </Button>
                        )}
                        {tournament.status == 'started' && (
                            <Button variant="destructive" onClick={endTournament} className="rounded-full">
                                End tournament
                            </Button>
                        )}
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-full border-lime-300/30 bg-lime-300/10 text-lime-200 hover:bg-lime-300/20 hover:text-lime-100"
                        >
                            <Link href={`/tournaments/${tournament.open_id}/edit`} prefetch>
                                Edit
                            </Link>
                        </Button>
                        <Button variant="destructive" type="button" className="rounded-full" onClick={() => setConfirmingTournamentDelete(true)}>
                            Delete
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="rounded-2xl border-black/8 bg-white/70 shadow-none lg:col-span-2 dark:border-white/8 dark:bg-white/[0.04]">
                        <CardHeader>
                            <CardTitle>Overview</CardTitle>
                            <CardDescription>{tournament.description || 'No description provided.'}</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm md:grid-cols-2">
                            <div className="space-y-1">
                                <p className="text-muted-foreground">Status</p>
                                <TournamentStatusBadge status={tournament.status} />
                            </div>
                            <div className="space-y-1">
                                <p className="text-muted-foreground">Mode</p>
                                <p>{toDisplayLabel(tournament.mode_type)}</p>
                            </div>
                            {isSwiss && (
                                <form
                                    className="grid gap-2 md:col-span-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        swissRoundsForm.put(route('tournament.swiss-rounds.update', tournament.open_id), { preserveScroll: true });
                                    }}
                                >
                                    <Label htmlFor="detail-swiss-rounds">Swiss rounds</Label>
                                    <div className="flex flex-wrap items-end gap-3">
                                        <Input
                                            id="detail-swiss-rounds"
                                            type="number"
                                            min={minimumSwissRounds}
                                            step={1}
                                            required
                                            className="w-full sm:w-36"
                                            value={swissRoundsForm.data.swiss_rounds}
                                            onChange={(event) => swissRoundsForm.setData('swiss_rounds', event.target.value)}
                                            disabled={swissRoundsForm.processing || tournament.status === 'ended'}
                                        />
                                        <Button
                                            type="submit"
                                            disabled={swissRoundsForm.processing || tournament.status === 'ended'}
                                            className="rounded-full bg-[#172012] px-6 font-semibold text-white hover:bg-[#2a3522] dark:bg-lime-400 dark:text-[#10140c] dark:hover:bg-lime-300"
                                        >
                                            {swissRoundsForm.processing ? 'Saving rounds…' : 'Save rounds'}
                                        </Button>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        The next round is generated when the current round is complete. Existing matches are preserved.
                                    </p>
                                    <InputError message={swissRoundsForm.errors.swiss_rounds} />
                                </form>
                            )}
                            <div className="space-y-1">
                                <p className="text-muted-foreground">Start At</p>
                                <p>{toDisplayDate(tournament.start_at)}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-muted-foreground">End At</p>
                                <p>{toDisplayDate(tournament.end_at)}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-muted-foreground">Check-in Required</p>
                                <p>{tournament.require_check_in ? 'Yes' : 'No'}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-muted-foreground">Check-in Time</p>
                                <p>{tournament.check_in_time || '-'}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-2xl border-black/8 bg-white/70 shadow-none dark:border-white/8 dark:bg-white/[0.04]">
                        <CardHeader>
                            <CardTitle>Stats</CardTitle>
                            <CardDescription>Current bracket summary.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <p>Players: {tournament.players_count}</p>
                            <p>Matches: {tournament.matches_count}</p>
                            <p>Max entry: {tournament.max_entry ?? '-'}</p>
                            {isDoubleElimination && <p>Split participants: {tournament.split_participant ? 'Yes' : 'No'}</p>}
                            {isFreeForAll && <p>Participants per match: {tournament.ffa_heat_size ?? '-'}</p>}
                            {isFreeForAll && <p>Advance per match: {tournament.ffa_advance_count ?? '-'}</p>}
                            {isRoundRobin && <p>Head to head count: {tournament.head_to_head_count ?? '-'}</p>}
                            {showRankAndPoints && <p>Rank by: {toDisplayLabel(tournament.rank_by)}</p>}
                            {showRankAndPoints && <p>Points per match win: {tournament.points_per_match_win ?? '-'}</p>}
                            {showRankAndPoints && <p>Points per match tie: {tournament.points_per_match_tie ?? '-'}</p>}
                            {showRankAndPoints && <p>Points per set win: {tournament.points_per_set_win ?? '-'}</p>}
                            {showRankAndPoints && <p>Points per set tie: {tournament.points_per_set_tie ?? '-'}</p>}
                            {showRankAndPoints && <p>Points per bye: {tournament.points_per_bye ?? '-'}</p>}
                            {isSwiss && <p>Swiss rounds: {tournament.swiss_rounds ?? '-'}</p>}
                        </CardContent>
                    </Card>
                </div>

                {tournament.status === 'ended' && (
                    <Card className="overflow-hidden rounded-2xl border-black/8 bg-[#172012] text-white shadow-xl shadow-black/10 dark:border-white/8">
                        <CardHeader className="border-b border-white/8">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-xl bg-lime-400 text-[#10140c]">
                                    <Trophy className="size-5" />
                                </span>
                                <div>
                                    <CardTitle>Final rankings</CardTitle>
                                    <CardDescription className="mt-1 text-white/45">Final placements for {tournament.name}.</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {finalRankings.length === 0 ? (
                                <p className="text-sm text-white/45">Final rankings are not available yet.</p>
                            ) : (
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {finalRankings.map((player) => (
                                        <div
                                            key={player.id}
                                            className={`flex items-center gap-4 rounded-2xl border p-4 ${
                                                player.final_rank === 1
                                                    ? 'border-lime-300/30 bg-lime-300/10'
                                                    : player.final_rank === 2
                                                      ? 'border-white/20 bg-white/8'
                                                      : player.final_rank === 3
                                                        ? 'border-amber-500/25 bg-amber-500/10'
                                                        : 'border-white/10 bg-white/[0.035]'
                                            }`}
                                        >
                                            <span
                                                className={`grid size-10 shrink-0 place-items-center rounded-xl font-bold ${player.final_rank === 1 ? 'bg-lime-400 text-[#10140c]' : 'bg-white/10 text-white/70'}`}
                                            >
                                                {player.final_rank && player.final_rank <= 3 ? <Medal className="size-5" /> : player.final_rank}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="truncate font-semibold">{player.name}</p>
                                                <p className="mt-0.5 text-xs text-white/40">
                                                    {player.final_rank === 1 ? 'Champion' : `Place #${player.final_rank}`}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 xl:grid-cols-1">
                    <Collapsible open={playersOpen} onOpenChange={setPlayersOpen}>
                        <Card className="rounded-2xl border-black/8 bg-white/70 shadow-none dark:border-white/8 dark:bg-white/[0.04]">
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>Players</CardTitle>
                                        <CardDescription>Registered participants in this tournament.</CardDescription>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {canDeletePlayers && bulkDeleteForm.data.player_ids.length > 0 && (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                type="button"
                                                className="rounded-full"
                                                onClick={() => setConfirmingBulkDelete(true)}
                                            >
                                                <Trash2 className="size-3.5" />
                                                Delete {bulkDeleteForm.data.player_ids.length}
                                            </Button>
                                        )}
                                        {tournament.status !== 'started' && tournament.status !== 'ended' && (
                                            <BulkAddPlayerModal
                                                open={openBulkAddPlayerModal}
                                                onOpenChange={setOpenBulkAddPlayerModal}
                                                tournamentOpenId={tournament.open_id}
                                            />
                                        )}
                                        <CollapsibleTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="button"
                                                className="gap-2 rounded-full text-green-800 hover:bg-lime-300/20 hover:text-green-950 dark:text-lime-300 dark:hover:bg-lime-300/10"
                                            >
                                                {playersOpen ? 'Hide' : 'Show'}
                                                <ChevronDownIcon className={`size-4 transition-transform ${playersOpen ? 'rotate-180' : ''}`} />
                                            </Button>
                                        </CollapsibleTrigger>
                                    </div>
                                </div>
                            </CardHeader>
                            <CollapsibleContent>
                                <CardContent>
                                    {tournament.players.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">No players yet.</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {tournament.players.map((player) => (
                                                <div
                                                    key={player.id}
                                                    className="flex items-center justify-between rounded-xl border border-black/8 bg-white/60 p-3 dark:border-white/8 dark:bg-white/[0.025]"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        {canDeletePlayers && (
                                                            <Checkbox
                                                                aria-label={`Select ${player.name}`}
                                                                checked={bulkDeleteForm.data.player_ids.includes(player.id)}
                                                                onCheckedChange={(checked) => togglePlayerSelection(player.id, checked === true)}
                                                            />
                                                        )}
                                                        <div>
                                                            <p className="font-medium">{player.name}</p>
                                                            <p className="text-muted-foreground text-xs">Seed: {player.seed ?? '-'}</p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant={player.checked_in ? 'default' : 'outline'}>
                                                            {player.checked_in ? 'Checked In' : 'Pending'}
                                                        </Badge>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="rounded-full border-green-800/15 text-green-800 hover:border-lime-500/40 hover:bg-lime-300/20 dark:border-lime-300/20 dark:text-lime-300 dark:hover:bg-lime-300/10"
                                                            onClick={() => setEditingPlayer(player)}
                                                        >
                                                            Edit
                                                        </Button>
                                                        {canDeletePlayers && (
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                className="rounded-full"
                                                                onClick={() => setDeletingPlayer(player)}
                                                            >
                                                                Delete
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {tournament.status !== 'started' && tournament.status !== 'ended' && (
                        <Card className="overflow-hidden rounded-2xl border-black/8 bg-[#11150f] text-white shadow-none dark:border-white/8">
                            <CardHeader>
                                <CardTitle>Bracket preview</CardTitle>
                                <CardDescription>{previewNote}</CardDescription>
                                <p className="text-xs text-white/45">Drag a player onto another player to swap their seeds.</p>
                            </CardHeader>
                            <CardContent>
                                {previewRounds.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">Check in at least two players to preview the bracket.</p>
                                ) : (
                                    <div className="space-y-5 text-white">
                                        <PreviewBracketColumns rounds={upperPreviewRounds} swappingSeeds={swappingSeeds} onSwapSeeds={swapPlayerSeeds} />
                                        {lowerPreviewRounds.length > 0 && (
                                            <div className="border-t pt-4">
                                                <p className="mb-2 text-sm font-semibold">Lower bracket</p>
                                                <PreviewBracketColumns rounds={lowerPreviewRounds} swappingSeeds={swappingSeeds} onSwapSeeds={swapPlayerSeeds} />
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {(tournament.status === 'started' || tournament.status === 'ended') && (
                        <TournamentBracket
                            modeType={tournament.mode_type}
                            players={tournament.players}
                            matches={tournament.matches}
                            onAddScore={(match: TournamentBracketMatch) => setScoringMatch(match)}
                            scoresLocked={tournament.status === 'ended'}
                        />
                    )}
                </div>
            </div>

            <MatchScoreModal
                open={scoringMatch !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setScoringMatch(null);
                    }
                }}
                tournamentOpenId={tournament.open_id}
                modeType={tournament.mode_type}
                players={tournament.players}
                match={scoringMatch}
            />

            <EditPlayerModal
                open={editingPlayer !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingPlayer(null);
                    }
                }}
                tournamentOpenId={tournament.open_id}
                player={editingPlayer}
                checkInLocked={tournament.status === 'started' || tournament.status === 'ended'}
            />

            <Dialog open={deletingPlayer !== null} onOpenChange={(open) => !open && setDeletingPlayer(null)}>
                <DialogContent className="rounded-2xl border-black/10 bg-[#f8f9f3] dark:border-white/10 dark:bg-[#151913]">
                    <DialogTitle>Delete Player</DialogTitle>
                    <DialogDescription>
                        {deletingPlayer
                            ? `Are you sure you want to delete ${deletingPlayer.name}? This action cannot be undone.`
                            : 'Are you sure you want to delete this player?'}
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                variant="outline"
                                type="button"
                                className="rounded-full border-green-800/15 text-green-800 hover:bg-lime-300/20 dark:border-lime-300/20 dark:text-lime-300 dark:hover:bg-lime-300/10"
                                onClick={() => setDeletingPlayer(null)}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button variant="destructive" type="button" className="rounded-full" onClick={submitDeletePlayer} disabled={deleteProcessing}>
                            Delete player
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmingBulkDelete} onOpenChange={setConfirmingBulkDelete}>
                <DialogContent className="rounded-2xl border-black/10 bg-[#f8f9f3] dark:border-white/10 dark:bg-[#151913]">
                    <DialogTitle>Delete selected players?</DialogTitle>
                    <DialogDescription>
                        {bulkDeleteForm.data.player_ids.length} selected players will be removed. Existing bracket slots for those players may become
                        empty. This action cannot be undone.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline" type="button" className="rounded-full">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            type="button"
                            className="rounded-full"
                            onClick={submitBulkDelete}
                            disabled={bulkDeleteForm.processing}
                        >
                            Delete {bulkDeleteForm.data.player_ids.length} players
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmingTournamentDelete} onOpenChange={setConfirmingTournamentDelete}>
                <DialogContent className="rounded-2xl border-black/10 bg-[#f8f9f3] dark:border-white/10 dark:bg-[#151913]">
                    <DialogTitle>Delete Tournament</DialogTitle>
                    <DialogDescription>Are you sure you want to delete {tournament.name}? This action cannot be undone.</DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                variant="outline"
                                type="button"
                                className="rounded-full border-green-800/15 text-green-800 hover:bg-lime-300/20 dark:border-lime-300/20 dark:text-lime-300 dark:hover:bg-lime-300/10"
                                onClick={() => setConfirmingTournamentDelete(false)}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            type="button"
                            className="rounded-full"
                            onClick={submitDeleteTournament}
                            disabled={deleteProcessing}
                        >
                            Delete tournament
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

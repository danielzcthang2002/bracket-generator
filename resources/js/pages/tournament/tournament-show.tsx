import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { MatchScoreModal } from '@/pages/tournament/match-score-modal';
import { BulkAddPlayerModal, EditPlayerModal } from '@/pages/tournament/player-modals';
import { TournamentBracket, type TournamentBracketMatch } from '@/pages/tournament/tournament-bracket';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';

interface TournamentPlayer {
    id: number;
    name: string;
    checked_in: boolean;
    seed: number | null;
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

export default function TournamentShow({ tournament }: TournamentShowProps) {
    const [openAddPlayerModal, setOpenAddPlayerModal] = useState(false);
    const [openBulkAddPlayerModal, setOpenBulkAddPlayerModal] = useState(false);
    const [editingPlayer, setEditingPlayer] = useState<TournamentPlayer | null>(null);
    const [deletingPlayer, setDeletingPlayer] = useState<TournamentPlayer | null>(null);
    const [confirmingTournamentDelete, setConfirmingTournamentDelete] = useState(false);
    const [playersOpen, setPlayersOpen] = useState(false);
    const [scoringMatch, setScoringMatch] = useState<TournamentMatch | null>(null);
    const { delete: destroy, processing: deleteProcessing } = useForm({});
    const { post: postStartTournament, processing: startTournamentProcessing } = useForm();
    const { post: postEndTournament, processing: endTournamentProcessing } = useForm();
    const isDoubleElimination = tournament.mode_type === 'double_elimination';
    const isFreeForAll = tournament.mode_type === 'free_for_all';
    const isRoundRobin = tournament.mode_type === 'round_robin';
    const isSwiss = tournament.mode_type === 'swiss';
    const showRankAndPoints = isRoundRobin || isSwiss;

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

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">{tournament.name}</h1>
                        <p className="text-muted-foreground text-sm">Open ID: {tournament.open_id}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        {/* <AddPlayerModal open={openAddPlayerModal} onOpenChange={setOpenAddPlayerModal} tournamentOpenId={tournament.open_id} /> */}
                        <BulkAddPlayerModal
                            open={openBulkAddPlayerModal}
                            onOpenChange={setOpenBulkAddPlayerModal}
                            tournamentOpenId={tournament.open_id}
                        />
                        {tournament.status == 'pending' && <Button onClick={startTournament}>Start tournament</Button>}
                        {tournament.status == 'started' && <Button variant="destructive" onClick={endTournament}>End tournament</Button>}
                        <Button asChild>
                            <Link href={`/tournaments/${tournament.open_id}/edit`} prefetch>
                                Edit
                            </Link>
                        </Button>
                        <Button variant="destructive" type="button" onClick={() => setConfirmingTournamentDelete(true)}>
                            Delete
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Overview</CardTitle>
                            <CardDescription>{tournament.description || 'No description provided.'}</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm md:grid-cols-2">
                            <div className="space-y-1">
                                <p className="text-muted-foreground">Status</p>
                                <Badge variant="secondary">{toDisplayLabel(tournament.status)}</Badge>
                            </div>
                            <div className="space-y-1">
                                <p className="text-muted-foreground">Mode</p>
                                <p>{toDisplayLabel(tournament.mode_type)}</p>
                            </div>
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

                    <Card>
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

                <div className="grid gap-4 xl:grid-cols-1">
                    <Collapsible open={playersOpen} onOpenChange={setPlayersOpen}>
                        <Card>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>Players</CardTitle>
                                        <CardDescription>Registered participants in this tournament.</CardDescription>
                                    </div>
                                    <CollapsibleTrigger asChild>
                                        <Button variant="ghost" size="sm" type="button" className="gap-2">
                                            {playersOpen ? 'Hide' : 'Show'}
                                            <ChevronDownIcon className={`size-4 transition-transform ${playersOpen ? 'rotate-180' : ''}`} />
                                        </Button>
                                    </CollapsibleTrigger>
                                </div>
                            </CardHeader>
                            <CollapsibleContent>
                                <CardContent>
                                    {tournament.players.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">No players yet.</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {tournament.players.map((player) => (
                                                <div key={player.id} className="flex items-center justify-between rounded-md border p-3">
                                                    <div>
                                                        <p className="font-medium">{player.name}</p>
                                                        <p className="text-muted-foreground text-xs">Seed: {player.seed ?? '-'}</p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant={player.checked_in ? 'default' : 'outline'}>
                                                            {player.checked_in ? 'Checked In' : 'Pending'}
                                                        </Badge>
                                                        <Button variant="outline" size="sm" onClick={() => setEditingPlayer(player)}>
                                                            Edit
                                                        </Button>
                                                        <Button variant="destructive" size="sm" onClick={() => setDeletingPlayer(player)}>
                                                            Delete
                                                        </Button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    <TournamentBracket
                        modeType={tournament.mode_type}
                        players={tournament.players}
                        matches={tournament.matches}
                        onAddScore={(match: TournamentBracketMatch) => setScoringMatch(match)}
                    />
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
            />

            <Dialog open={deletingPlayer !== null} onOpenChange={(open) => !open && setDeletingPlayer(null)}>
                <DialogContent>
                    <DialogTitle>Delete Player</DialogTitle>
                    <DialogDescription>
                        {deletingPlayer
                            ? `Are you sure you want to delete ${deletingPlayer.name}? This action cannot be undone.`
                            : 'Are you sure you want to delete this player?'}
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline" type="button" onClick={() => setDeletingPlayer(null)}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button variant="destructive" type="button" onClick={submitDeletePlayer} disabled={deleteProcessing}>
                            Delete player
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmingTournamentDelete} onOpenChange={setConfirmingTournamentDelete}>
                <DialogContent>
                    <DialogTitle>Delete Tournament</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete {tournament.name}? This action cannot be undone.
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline" type="button" onClick={() => setConfirmingTournamentDelete(false)}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button variant="destructive" type="button" onClick={submitDeleteTournament} disabled={deleteProcessing}>
                            Delete tournament
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

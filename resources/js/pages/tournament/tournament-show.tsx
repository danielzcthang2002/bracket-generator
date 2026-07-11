import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { MatchScoreModal } from '@/pages/tournament/match-score-modal';
import { TournamentBracket, type TournamentBracketMatch } from '@/pages/tournament/tournament-bracket';
import { AddPlayerModal, EditPlayerModal } from '@/pages/tournament/player-modals';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
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
    participants_per_match: number | null;
    head_to_head_count: number | null;
    rank_by: string | null;
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
    const [editingPlayer, setEditingPlayer] = useState<TournamentPlayer | null>(null);
    const [deletingPlayer, setDeletingPlayer] = useState<TournamentPlayer | null>(null);
    const [scoringMatch, setScoringMatch] = useState<TournamentMatch | null>(null);
    const { delete: destroy, processing: deleteProcessing } = useForm({});
    const { post: postStartTournament, processing: startTournamentProcessing } = useForm();

    const submitDeletePlayer = () => {
        if (!deletingPlayer) {
            return;
        }

        destroy(route('tournament.player.destroy', [tournament.open_id, deletingPlayer.id]), {
            preserveScroll: true,
            onSuccess: () => setDeletingPlayer(null),
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
                        <AddPlayerModal open={openAddPlayerModal} onOpenChange={setOpenAddPlayerModal} tournamentOpenId={tournament.open_id} />
                        {tournament.status == 'pending' && <Button onClick={startTournament}>Start tournament</Button>}
                        <Button asChild>
                            <Link href={`/tournaments/${tournament.open_id}/edit`} prefetch>
                                Edit tournament
                            </Link>
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
                            <p>Split participants: {tournament.split_participant ? 'Yes' : 'No'}</p>
                            <p>Participants per match: {tournament.participants_per_match ?? '-'}</p>
                            <p>Head to head count: {tournament.head_to_head_count ?? '-'}</p>
                            <p>Rank by: {toDisplayLabel(tournament.rank_by)}</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Players</CardTitle>
                            <CardDescription>Registered participants in this tournament.</CardDescription>
                        </CardHeader>
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
                    </Card>

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
        </AppLayout>
    );
}

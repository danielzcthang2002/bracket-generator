import { TournamentStatusBadge } from '@/components/tournament-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Plus, Trophy, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tournament',
        href: '/tournaments',
    },
    {
        title: 'Lists',
        href: '/tournaments',
    },
];

interface TournamentListItem {
    id: number;
    name: string;
    open_id: string;
    mode_type: string | null;
    status: string | null;
    start_at: string | null;
    end_at: string | null;
    players_count: number;
    matches_count: number;
    created_at: string | null;
}

interface TournamentIndexProps {
    tournaments: TournamentListItem[];
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

export default function TournamentIndex({ tournaments }: TournamentIndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tournaments" />

            <div className="flex h-full flex-1 flex-col gap-5 bg-[#f4f6ed] p-4 md:p-6 dark:bg-[#0b0e09]">
                <div className="relative overflow-hidden rounded-3xl bg-[#172012] p-6 text-white shadow-xl shadow-black/10 sm:flex sm:items-end sm:justify-between sm:p-8">
                    <div className="absolute -top-20 right-10 size-56 rounded-full bg-lime-400/15 blur-3xl" />
                    <div className="relative">
                        <p className="text-sm font-semibold text-lime-300">Tournament workspace</p>
                        <h1 className="mt-1 text-3xl font-bold tracking-[-0.04em]">Your tournaments</h1>
                        <p className="mt-2 text-sm text-white/50">Create, manage, and follow every competition from one place.</p>
                    </div>
                    <Button asChild className="relative mt-6 rounded-full bg-lime-400 px-5 font-semibold text-[#10140c] hover:bg-lime-300 sm:mt-0">
                        <Link href="/tournaments/create" prefetch>
                            <Plus className="mr-1 size-4" /> Create tournament
                        </Link>
                    </Button>
                </div>
                <Card className="rounded-2xl border-black/8 bg-white/70 shadow-none dark:border-white/8 dark:bg-white/[0.04]">
                    <CardHeader>
                        <CardTitle>All tournaments</CardTitle>
                        <CardDescription>Browse your bracket setups and current activity.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {tournaments.length === 0 ? (
                            <div className="flex flex-col items-center rounded-2xl border border-dashed border-black/10 py-14 text-center dark:border-white/10">
                                <span className="grid size-12 place-items-center rounded-2xl bg-lime-300/25 text-lime-700 dark:text-lime-300">
                                    <Trophy className="size-6" />
                                </span>
                                <p className="mt-4 font-semibold">No tournaments yet</p>
                                <p className="text-muted-foreground mt-1 text-sm">Create your first tournament to get started.</p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {tournaments.map((tournament) => (
                                    <div
                                        key={tournament.id}
                                        className="group flex flex-col gap-4 rounded-2xl border border-black/8 bg-white/60 p-5 transition hover:-translate-y-0.5 hover:border-lime-500/30 hover:shadow-lg hover:shadow-black/5 md:flex-row md:items-center md:justify-between dark:border-white/8 dark:bg-white/[0.025]"
                                    >
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-base font-semibold">{tournament.name}</h3>
                                                <TournamentStatusBadge status={tournament.status} />
                                            </div>
                                            <p className="text-muted-foreground text-sm">Open ID: {tournament.open_id}</p>
                                            <div className="text-muted-foreground flex flex-wrap gap-4 text-xs">
                                                <span className="flex items-center gap-1.5">
                                                    <Users className="size-3.5 text-lime-600 dark:text-lime-400" />
                                                    {tournament.players_count} players
                                                </span>
                                                <span>Mode: {toDisplayLabel(tournament.mode_type)}</span>
                                                <span>Matches: {tournament.matches_count}</span>
                                                <span>Start: {toDisplayDate(tournament.start_at)}</span>
                                            </div>
                                        </div>

                                        <Button asChild variant="ghost" className="rounded-full group-hover:bg-lime-300/25">
                                            <Link href={`/tournaments/${tournament.open_id}`} prefetch>
                                                View details <ArrowRight className="ml-1 size-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

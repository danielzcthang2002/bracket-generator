import { TournamentStatusBadge } from '@/components/tournament-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Plus, Trophy, Users } from 'lucide-react';
import { type FormEvent, useState } from 'react';

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

const secondaryButtonClassName =
    'rounded-full border-green-800/15 text-green-800 hover:bg-lime-300/20 dark:border-lime-300/20 dark:text-lime-300 dark:hover:bg-lime-300/10';

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
    tournaments: {
        data: TournamentListItem[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string; mode: string; status: string };
    modeOptions: string[];
    statusOptions: string[];
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

export default function TournamentIndex({ tournaments, filters, modeOptions, statusOptions }: TournamentIndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [mode, setMode] = useState(filters.mode);
    const [status, setStatus] = useState(filters.status);
    const [processing, setProcessing] = useState(false);
    const hasFilters = Boolean(filters.search || filters.mode || filters.status);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/tournaments',
            { search: search.trim(), mode, status },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    }

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
                        <form onSubmit={applyFilters} className="mb-6 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                            <div className="grid min-w-48 flex-1 gap-2">
                                <Label htmlFor="tournament-search">Search tournaments</Label>
                                <Input
                                    id="tournament-search"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Name or Open ID"
                                    maxLength={255}
                                    disabled={processing}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tournament-mode">Mode</Label>
                                <Select value={mode || 'all'} onValueChange={(value) => setMode(value === 'all' ? '' : value)} disabled={processing}>
                                    <SelectTrigger id="tournament-mode" className="w-full sm:w-48">
                                        <SelectValue placeholder="All modes" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All modes</SelectItem>
                                        {modeOptions.map((value) => (
                                            <SelectItem key={value} value={value}>
                                                {toDisplayLabel(value)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tournament-status">Status</Label>
                                <Select
                                    value={status || 'all'}
                                    onValueChange={(value) => setStatus(value === 'all' ? '' : value)}
                                    disabled={processing}
                                >
                                    <SelectTrigger id="tournament-status" className="w-full sm:w-36">
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All statuses</SelectItem>
                                        {statusOptions.map((value) => (
                                            <SelectItem key={value} value={value}>
                                                {toDisplayLabel(value)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-full bg-[#172012] px-6 font-semibold text-white hover:bg-[#2a3522] dark:bg-lime-400 dark:text-[#10140c] dark:hover:bg-lime-300"
                            >
                                {processing ? 'Applying filters…' : 'Apply filters'}
                            </Button>
                            {hasFilters && (
                                <Button asChild variant="outline" className={secondaryButtonClassName}>
                                    <Link href="/tournaments" preserveScroll>
                                        Clear filters
                                    </Link>
                                </Button>
                            )}
                        </form>
                        {tournaments.data.length === 0 ? (
                            <div className="flex flex-col items-center rounded-2xl border border-dashed border-black/10 py-14 text-center dark:border-white/10">
                                <span className="grid size-12 place-items-center rounded-2xl bg-lime-300/25 text-lime-700 dark:text-lime-300">
                                    <Trophy className="size-6" />
                                </span>
                                <p className="mt-4 font-semibold">{hasFilters ? 'No matching tournaments' : 'No tournaments yet'}</p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {hasFilters ? 'Try another search or clear your filters.' : 'Create your first tournament to get started.'}
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {tournaments.data.map((tournament) => (
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
                        {tournaments.total > 0 && (
                            <nav aria-label="Tournament pagination" className="mt-6 flex flex-wrap items-center justify-between gap-3">
                                <p className="text-muted-foreground text-sm">
                                    Showing {tournaments.from ?? 0}–{tournaments.to ?? 0} of {tournaments.total} tournaments
                                </p>
                                <div className="flex items-center gap-3">
                                    {tournaments.prev_page_url ? (
                                        <Button asChild variant="outline" className={secondaryButtonClassName}>
                                            <Link href={tournaments.prev_page_url} preserveScroll>
                                                Previous
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button variant="outline" className={secondaryButtonClassName} disabled>
                                            Previous
                                        </Button>
                                    )}
                                    <span className="text-sm">
                                        Page {tournaments.current_page} of {tournaments.last_page}
                                    </span>
                                    {tournaments.next_page_url ? (
                                        <Button asChild variant="outline" className={secondaryButtonClassName}>
                                            <Link href={tournaments.next_page_url} preserveScroll>
                                                Next
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button variant="outline" className={secondaryButtonClassName} disabled>
                                            Next
                                        </Button>
                                    )}
                                </div>
                            </nav>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

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

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-3">
                            <CardTitle>Tournaments</CardTitle>
                            <Button asChild>
                                <Link href="/tournaments/create" prefetch>
                                    Create tournament
                                </Link>
                            </Button>
                        </div>
                        <CardDescription>Browse all tournaments and open details for each bracket setup.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {tournaments.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No tournaments available yet.</p>
                        ) : (
                            <div className="space-y-3">
                                {tournaments.map((tournament) => (
                                    <div
                                        key={tournament.id}
                                        className="flex flex-col gap-3 rounded-lg border p-4 md:flex-row md:items-center md:justify-between"
                                    >
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-base font-semibold">{tournament.name}</h3>
                                                <Badge variant="secondary">{toDisplayLabel(tournament.status)}</Badge>
                                            </div>
                                            <p className="text-muted-foreground text-sm">Open ID: {tournament.open_id}</p>
                                            <div className="text-muted-foreground flex flex-wrap gap-4 text-xs">
                                                <span>Mode: {toDisplayLabel(tournament.mode_type)}</span>
                                                <span>Players: {tournament.players_count}</span>
                                                <span>Matches: {tournament.matches_count}</span>
                                                <span>Start: {toDisplayDate(tournament.start_at)}</span>
                                            </div>
                                        </div>

                                        <Button asChild variant="outline">
                                            <Link href={`/tournaments/${tournament.open_id}`} prefetch>
                                                View details
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

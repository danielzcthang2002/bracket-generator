import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowRight, CalendarDays, CheckCircle2, Trophy, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface ChartDatum {
    label: string;
    value: number;
}
interface StatusDatum extends ChartDatum {
    key: string;
}
interface RecentTournament {
    id: number;
    name: string;
    open_id: string;
    mode_type: string | null;
    status: string | null;
    start_at: string | null;
    players_count: number;
    matches_count: number;
    completed_matches_count: number;
}
interface DashboardProps {
    stats: { tournaments: number; players: number; completed_matches: number; total_matches: number; active_tournaments: number };
    tournamentTrend: ChartDatum[];
    statusBreakdown: StatusDatum[];
    modeBreakdown: ChartDatum[];
    recentTournaments: RecentTournament[];
}

const statusColors: Record<string, string> = {
    pending: 'bg-slate-400',
    open: 'bg-sky-500',
    closed: 'bg-zinc-500',
    seeding: 'bg-amber-500',
    started: 'bg-emerald-500',
    ended: 'bg-violet-500',
};

function displayLabel(value: string | null) {
    return value
        ? value
              .split('_')
              .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
              .join(' ')
        : 'Not set';
}

function displayDate(value: string | null) {
    return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : 'Not scheduled';
}

function EmptyChart() {
    return <div className="text-muted-foreground flex h-44 items-center justify-center rounded-lg border border-dashed text-sm">No data yet</div>;
}

export default function Dashboard({ stats, tournamentTrend, statusBreakdown, modeBreakdown, recentTournaments }: DashboardProps) {
    const completionRate = stats.total_matches === 0 ? 0 : Math.round((stats.completed_matches / stats.total_matches) * 100);
    const maxTrend = Math.max(...tournamentTrend.map((item) => item.value), 1);
    const maxMode = Math.max(...modeBreakdown.map((item) => item.value), 1);
    const hasTournamentData = stats.tournaments > 0;
    const statCards = [
        {
            label: 'Tournaments',
            value: stats.tournaments,
            detail: `${stats.active_tournaments} active now`,
            icon: Trophy,
            tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        },
        {
            label: 'Participants',
            value: stats.players,
            detail: 'Across all tournaments',
            icon: Users,
            tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
        },
        {
            label: 'Completed matches',
            value: stats.completed_matches,
            detail: `${stats.total_matches} matches generated`,
            icon: CheckCircle2,
            tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Completion rate',
            value: `${completionRate}%`,
            detail: stats.total_matches ? `${stats.total_matches - stats.completed_matches} matches remaining` : 'No matches generated',
            icon: Activity,
            tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-muted-foreground text-sm font-medium">Tournament overview</p>
                        <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
                        <p className="text-muted-foreground mt-1 text-sm">Track participation, formats, and match progress at a glance.</p>
                    </div>
                    <Button asChild>
                        <Link href="/tournaments/create">Create tournament</Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {statCards.map((stat) => (
                        <Card key={stat.label} className="gap-4 py-5">
                            <CardContent className="flex items-start justify-between px-5">
                                <div>
                                    <p className="text-muted-foreground text-sm font-medium">{stat.label}</p>
                                    <p className="mt-2 text-3xl font-semibold tracking-tight">{stat.value}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">{stat.detail}</p>
                                </div>
                                <div className={`rounded-lg p-2.5 ${stat.tone}`}>
                                    <stat.icon className="size-5" />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 xl:grid-cols-5">
                    <Card className="xl:col-span-3">
                        <CardHeader>
                            <CardTitle>Tournament activity</CardTitle>
                            <CardDescription>New tournaments created over the last six months.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!hasTournamentData ? (
                                <EmptyChart />
                            ) : (
                                <div className="flex h-52 items-end gap-3 pt-6 sm:gap-6">
                                    {tournamentTrend.map((item) => (
                                        <div key={item.label} className="flex h-full flex-1 flex-col items-center justify-end gap-2">
                                            <span className="text-muted-foreground text-xs font-medium">{item.value}</span>
                                            <div
                                                className="bg-primary/90 hover:bg-primary w-full max-w-14 rounded-t-md transition-colors"
                                                style={{ height: `${Math.max((item.value / maxTrend) * 100, item.value ? 8 : 2)}%` }}
                                                title={`${item.value} tournaments`}
                                            />
                                            <span className="text-muted-foreground text-xs">{item.label}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle>Tournament status</CardTitle>
                            <CardDescription>Current lifecycle distribution.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!hasTournamentData ? (
                                <EmptyChart />
                            ) : (
                                <div className="space-y-4">
                                    <div className="bg-muted flex h-3 overflow-hidden rounded-full">
                                        {statusBreakdown
                                            .filter((item) => item.value > 0)
                                            .map((item) => (
                                                <div
                                                    key={item.key}
                                                    className={statusColors[item.key]}
                                                    style={{ width: `${(item.value / stats.tournaments) * 100}%` }}
                                                    title={`${item.label}: ${item.value}`}
                                                />
                                            ))}
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        {statusBreakdown.map((item) => (
                                            <div key={item.key} className="flex items-center justify-between gap-2 rounded-md border px-3 py-2">
                                                <span className="flex items-center gap-2 text-sm">
                                                    <span className={`size-2 rounded-full ${statusColors[item.key]}`} />
                                                    {item.label}
                                                </span>
                                                <span className="font-semibold tabular-nums">{item.value}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-5">
                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle>Popular formats</CardTitle>
                            <CardDescription>Tournaments grouped by bracket format.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!hasTournamentData ? (
                                <EmptyChart />
                            ) : (
                                <div className="space-y-4">
                                    {modeBreakdown.map((item) => (
                                        <div key={item.label} className="space-y-1.5">
                                            <div className="flex justify-between text-sm">
                                                <span>{item.label}</span>
                                                <span className="text-muted-foreground tabular-nums">{item.value}</span>
                                            </div>
                                            <div className="bg-muted h-2 overflow-hidden rounded-full">
                                                <div
                                                    className="bg-chart-2 h-full rounded-full"
                                                    style={{ width: `${(item.value / maxMode) * 100}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="xl:col-span-3">
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <div>
                                <CardTitle>Recent tournaments</CardTitle>
                                <CardDescription className="mt-1.5">Your latest bracket activity and match progress.</CardDescription>
                            </div>
                            <Button asChild variant="ghost" size="sm">
                                <Link href="/tournaments">
                                    View all <ArrowRight className="ml-1 size-4" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentTournaments.length === 0 ? (
                                <div className="text-muted-foreground flex flex-col items-center gap-3 rounded-lg border border-dashed py-10 text-center text-sm">
                                    <Trophy className="size-8 opacity-40" />
                                    <span>Create your first tournament to see activity here.</span>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {recentTournaments.map((tournament) => {
                                        const progress = tournament.matches_count
                                            ? Math.round((tournament.completed_matches_count / tournament.matches_count) * 100)
                                            : 0;
                                        return (
                                            <Link
                                                key={tournament.id}
                                                href={`/tournaments/${tournament.open_id}`}
                                                className="group flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <p className="truncate font-medium group-hover:underline">{tournament.name}</p>
                                                        <Badge variant="secondary">{displayLabel(tournament.status)}</Badge>
                                                    </div>
                                                    <p className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 text-xs">
                                                        <span>{displayLabel(tournament.mode_type)}</span>
                                                        <span>{tournament.players_count} players</span>
                                                        <span className="flex items-center gap-1">
                                                            <CalendarDays className="size-3" />
                                                            {displayDate(tournament.start_at)}
                                                        </span>
                                                    </p>
                                                </div>
                                                <div className="w-full shrink-0 sm:w-28">
                                                    <div className="mb-1 flex justify-between text-xs">
                                                        <span className="text-muted-foreground">Matches</span>
                                                        <span>{progress}%</span>
                                                    </div>
                                                    <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                                        <div className="bg-primary h-full rounded-full" style={{ width: `${progress}%` }} />
                                                    </div>
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

import { Badge } from '@/components/ui/badge';

const statusStyles: Record<string, string> = {
    pending: 'border-amber-500/25 bg-amber-400/15 text-amber-700 dark:border-amber-300/25 dark:text-amber-200',
    open: 'border-sky-500/25 bg-sky-400/15 text-sky-700 dark:border-sky-300/25 dark:text-sky-200',
    closed: 'border-rose-500/25 bg-rose-400/15 text-rose-700 dark:border-rose-300/25 dark:text-rose-200',
    seeding: 'border-violet-500/25 bg-violet-400/15 text-violet-700 dark:border-violet-300/25 dark:text-violet-200',
    started: 'border-lime-500/30 bg-lime-400/20 text-lime-800 dark:border-lime-300/30 dark:text-lime-300',
    ended: 'border-emerald-500/25 bg-emerald-400/15 text-emerald-700 dark:border-emerald-300/25 dark:text-emerald-200',
};

function labelForStatus(status: string | null) {
    if (!status) {
        return 'Not set';
    }

    return status
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export function TournamentStatusBadge({ status }: { status: string | null }) {
    const style = status ? statusStyles[status] : undefined;

    return (
        <Badge
            variant="outline"
            className={`rounded-full px-2.5 py-0.5 font-semibold ${style ?? 'border-black/10 bg-black/5 text-black/50 dark:border-white/10 dark:bg-white/5 dark:text-white/50'}`}
        >
            <span className="mr-1.5 size-1.5 rounded-full bg-current opacity-75" />
            {labelForStatus(status)}
        </Badge>
    );
}

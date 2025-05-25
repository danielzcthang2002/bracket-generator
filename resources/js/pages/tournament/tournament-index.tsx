import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

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

export default function TournamentIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tournaments" />
        </AppLayout>
    );
}

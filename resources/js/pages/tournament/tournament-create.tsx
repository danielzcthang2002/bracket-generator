import AppLayout from '@/layouts/app-layout';
import TournamentForm, { type TournamentFormData } from '@/pages/tournament/tournament-form';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface TournamentCreateProps {
    modeOptions: string[];
    statusOptions: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tournament',
        href: '/tournaments',
    },
    {
        title: 'Create',
        href: '/tournaments/create',
    },
];

const initialData: TournamentFormData = {
    name: '',
    mode_type: 'single_elimination',
    description: '',
    start_at: '',
    end_at: '',
    require_check_in: false,
    check_in_time: '',
    max_entry: '',
    status: 'pending',
    split_participant: false,
    ffa_heat_size: '',
    ffa_advance_count: '',
    head_to_head_count: '',
    rank_by: '',
    points_per_match_win: '',
    points_per_match_tie: '',
    points_per_set_win: '',
    points_per_set_tie: '',
    points_per_bye: '',
    swiss_rounds: '',
};

export default function TournamentCreate({ modeOptions, statusOptions }: TournamentCreateProps) {
    const { data, setData, post, processing, errors } = useForm<TournamentFormData>(initialData);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(route('tournament.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Tournament" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <TournamentForm
                    title="Create Tournament"
                    description="Configure your tournament settings and save to start adding players."
                    submitLabel="Create tournament"
                    modeOptions={modeOptions}
                    statusOptions={statusOptions}
                    data={data}
                    setData={setData}
                    processing={processing}
                    errors={errors}
                    onSubmit={onSubmit}
                />
            </div>
        </AppLayout>
    );
}

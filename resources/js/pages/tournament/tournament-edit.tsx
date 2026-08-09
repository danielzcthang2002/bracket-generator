import AppLayout from '@/layouts/app-layout';
import TournamentForm, { type TournamentFormData } from '@/pages/tournament/tournament-form';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface TournamentEditProps {
    modeOptions: string[];
    statusOptions: string[];
    tournament: TournamentFormData & {
        open_id: string;
    };
}

export default function TournamentEdit({ modeOptions, statusOptions, tournament }: TournamentEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Tournament',
            href: '/tournaments',
        },
        {
            title: tournament.name,
            href: `/tournaments/${tournament.open_id}`,
        },
        {
            title: 'Edit',
            href: `/tournaments/${tournament.open_id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm<TournamentFormData>({
        name: tournament.name ?? '',
        mode_type: tournament.mode_type ?? 'single_elimination',
        description: tournament.description ?? '',
        start_at: tournament.start_at ?? '',
        end_at: tournament.end_at ?? '',
        require_check_in: Boolean(tournament.require_check_in),
        check_in_time: tournament.check_in_time ?? '',
        max_entry: tournament.max_entry ?? '',
        status: tournament.status ?? 'pending',
        split_participant: Boolean(tournament.split_participant),
        ffa_heat_size: tournament.ffa_heat_size ?? '',
        ffa_advance_count: tournament.ffa_advance_count ?? '',
        head_to_head_count: tournament.head_to_head_count ?? '',
        rank_by: tournament.rank_by ?? '',
        points_per_match_win: tournament.points_per_match_win ?? '',
        points_per_match_tie: tournament.points_per_match_tie ?? '',
        points_per_set_win: tournament.points_per_set_win ?? '',
        points_per_set_tie: tournament.points_per_set_tie ?? '',
        points_per_bye: tournament.points_per_bye ?? '',
        swiss_rounds: tournament.swiss_rounds ?? '',
    });

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        put(route('tournament.update', tournament.open_id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${tournament.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <TournamentForm
                    title="Edit Tournament"
                    description="Update tournament configuration and save changes."
                    submitLabel="Save changes"
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

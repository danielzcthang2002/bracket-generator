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
    const { data, setData, transform, post, processing, errors } = useForm<TournamentFormData>(initialData);

    const onSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((formData) => {
            if (formData.mode_type !== 'swiss' || Number(formData.swiss_rounds) >= 1) {
                return formData;
            }

            const maxEntry = Math.max(1, Number(formData.max_entry) || 1);

            return {
                ...formData,
                swiss_rounds: String(Math.max(1, Math.ceil(Math.log2(maxEntry)))),
            };
        });

        post(route('tournament.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Tournament" />
            <div className="flex h-full flex-1 flex-col gap-4 bg-[#f4f6ed] p-4 md:p-6 dark:bg-[#0b0e09]">
                <TournamentForm
                    title="Build a new tournament"
                    description="Choose a format, set your rules, and get ready to add the field."
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

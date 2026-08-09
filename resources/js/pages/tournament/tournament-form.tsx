import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type FormEvent } from 'react';

export interface TournamentFormData {
    [key: string]: string | boolean;
    name: string;
    mode_type: string;
    description: string;
    start_at: string;
    end_at: string;
    require_check_in: boolean;
    check_in_time: string;
    max_entry: string;
    status: string;
    split_participant: boolean;
    ffa_heat_size: string;
    ffa_advance_count: string;
    head_to_head_count: string;
    rank_by: string;
    points_per_match_win: string;
    points_per_match_tie: string;
    points_per_set_win: string;
    points_per_set_tie: string;
    points_per_bye: string;
    swiss_rounds: string;
}

interface TournamentFormProps {
    title: string;
    description: string;
    submitLabel: string;
    modeOptions: string[];
    statusOptions: string[];
    data: TournamentFormData;
    processing: boolean;
    errors: Partial<Record<keyof TournamentFormData, string>>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    setData: {
        (key: keyof TournamentFormData, value: string | boolean): void;
        (data: TournamentFormData): void;
        (data: (previousData: TournamentFormData) => TournamentFormData): void;
    };
}

function toLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export default function TournamentForm({
    title,
    description,
    submitLabel,
    modeOptions,
    statusOptions,
    data,
    processing,
    errors,
    onSubmit,
    setData,
}: TournamentFormProps) {
    const isDoubleElimination = data.mode_type === 'double_elimination';
    const isFreeForAll = data.mode_type === 'free_for_all';
    const isRoundRobin = data.mode_type === 'round_robin';
    const isSwiss = data.mode_type === 'swiss';
    const showRankAndPoints = isRoundRobin || isSwiss;

    return (
        <form onSubmit={onSubmit} className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="name">Tournament Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            disabled={processing}
                            placeholder="Summer Championship"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="mode_type">Mode</Label>
                        <Select value={data.mode_type} onValueChange={(value) => setData('mode_type', value)}>
                            <SelectTrigger id="mode_type" disabled={processing}>
                                <SelectValue placeholder="Select mode" />
                            </SelectTrigger>
                            <SelectContent>
                                {modeOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {toLabel(option)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.mode_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                            <SelectTrigger id="status" disabled={processing}>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {toLabel(option)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            value={data.description}
                            onChange={(event) => setData('description', event.target.value)}
                            disabled={processing}
                            placeholder="Tournament notes and rules"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="start_at">Start At</Label>
                        <Input
                            id="start_at"
                            type="datetime-local"
                            value={data.start_at}
                            onChange={(event) => setData('start_at', event.target.value)}
                            disabled={processing}
                        />
                        <InputError message={errors.start_at} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="end_at">End At</Label>
                        <Input
                            id="end_at"
                            type="datetime-local"
                            value={data.end_at}
                            onChange={(event) => setData('end_at', event.target.value)}
                            disabled={processing}
                        />
                        <InputError message={errors.end_at} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="max_entry">Max Entry</Label>
                        <Input
                            id="max_entry"
                            type="number"
                            min={2}
                            value={data.max_entry}
                            onChange={(event) => setData('max_entry', event.target.value)}
                            disabled={processing}
                        />
                        <InputError message={errors.max_entry} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="check_in_time">Check-in Time (minutes)</Label>
                        <Input
                            id="check_in_time"
                            type="number"
                            min={1}
                            value={data.check_in_time}
                            onChange={(event) => setData('check_in_time', event.target.value)}
                            disabled={processing}
                        />
                        <InputError message={errors.check_in_time} />
                    </div>

                    {isFreeForAll && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="ffa_heat_size">Participants per Match</Label>
                                <Input
                                    id="ffa_heat_size"
                                    type="number"
                                    min={2}
                                    value={data.ffa_heat_size}
                                    onChange={(event) => setData('ffa_heat_size', event.target.value)}
                                    disabled={processing}
                                />
                                <InputError message={errors.ffa_heat_size} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="ffa_advance_count">Advance per Match</Label>
                                <Input
                                    id="ffa_advance_count"
                                    type="number"
                                    min={1}
                                    value={data.ffa_advance_count}
                                    onChange={(event) => setData('ffa_advance_count', event.target.value)}
                                    disabled={processing}
                                />
                                <InputError message={errors.ffa_advance_count} />
                            </div>
                        </>
                    )}

                    {isRoundRobin && (
                        <div className="grid gap-2">
                            <Label htmlFor="head_to_head_count">Head to Head Count</Label>
                            <Input
                                id="head_to_head_count"
                                type="number"
                                min={1}
                                value={data.head_to_head_count}
                                onChange={(event) => setData('head_to_head_count', event.target.value)}
                                disabled={processing}
                            />
                            <InputError message={errors.head_to_head_count} />
                        </div>
                    )}

                    {showRankAndPoints && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="rank_by">Rank By</Label>
                                <Input
                                    id="rank_by"
                                    value={data.rank_by}
                                    onChange={(event) => setData('rank_by', event.target.value)}
                                    disabled={processing}
                                    placeholder="points"
                                />
                                <InputError message={errors.rank_by} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="points_per_match_win">Points per Match Win</Label>
                                <Input
                                    id="points_per_match_win"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.points_per_match_win}
                                    onChange={(event) => setData('points_per_match_win', event.target.value)}
                                    disabled={processing}
                                />
                                <InputError message={errors.points_per_match_win} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="points_per_match_tie">Points per Match Tie</Label>
                                <Input
                                    id="points_per_match_tie"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.points_per_match_tie}
                                    onChange={(event) => setData('points_per_match_tie', event.target.value)}
                                    disabled={processing}
                                />
                                <InputError message={errors.points_per_match_tie} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="points_per_set_win">Points per Set Win</Label>
                                <Input
                                    id="points_per_set_win"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.points_per_set_win}
                                    onChange={(event) => setData('points_per_set_win', event.target.value)}
                                    disabled={processing}
                                />
                                <InputError message={errors.points_per_set_win} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="points_per_set_tie">Points per Set Tie</Label>
                                <Input
                                    id="points_per_set_tie"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.points_per_set_tie}
                                    onChange={(event) => setData('points_per_set_tie', event.target.value)}
                                    disabled={processing}
                                />
                                <InputError message={errors.points_per_set_tie} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="points_per_bye">Points per Bye</Label>
                                <Input
                                    id="points_per_bye"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.points_per_bye}
                                    onChange={(event) => setData('points_per_bye', event.target.value)}
                                    disabled={processing}
                                />
                                <InputError message={errors.points_per_bye} />
                            </div>
                        </>
                    )}

                    {isSwiss && (
                        <div className="grid gap-2">
                            <Label htmlFor="swiss_rounds">Swiss Rounds</Label>
                            <Input
                                id="swiss_rounds"
                                type="number"
                                min={1}
                                value={data.swiss_rounds}
                                onChange={(event) => setData('swiss_rounds', event.target.value)}
                                disabled={processing}
                            />
                            <InputError message={errors.swiss_rounds} />
                        </div>
                    )}

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="require_check_in"
                            checked={data.require_check_in}
                            onCheckedChange={(checked) => setData('require_check_in', Boolean(checked))}
                            disabled={processing}
                        />
                        <Label htmlFor="require_check_in">Require check-in</Label>
                    </div>

                    {isDoubleElimination && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="split_participant"
                                checked={data.split_participant}
                                onCheckedChange={(checked) => setData('split_participant', Boolean(checked))}
                                disabled={processing}
                            />
                            <Label htmlFor="split_participant">Split participants</Label>
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

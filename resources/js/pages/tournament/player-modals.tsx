import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { type FormEvent, useEffect } from 'react';

export interface PlayerModalFormData {
    [key: string]: string | boolean;
    name: string;
    checked_in: boolean;
}

interface PlayerSummary {
    id: number;
    name: string;
    checked_in: boolean;
}

interface AddPlayerModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tournamentOpenId: string;
}

interface BulkPlayerModalFormData {
    [key: string]: string;
    prefix: string;
    count: string;
}

export function AddPlayerModal({ open, onOpenChange, tournamentOpenId }: AddPlayerModalProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<PlayerModalFormData>({
        name: '',
        checked_in: false,
    });

    const closeModal = () => {
        onOpenChange(false);
        clearErrors();
        reset();
    };

    const submitAddPlayer = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post(route('tournament.player.store', tournamentOpenId), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    closeModal();
                    return;
                }

                onOpenChange(true);
            }}
        >
            <DialogTrigger asChild>
                <Button className="rounded-full bg-lime-400 font-semibold text-[#10140c] hover:bg-lime-300">Add player</Button>
            </DialogTrigger>
            <DialogContent className="rounded-2xl border-black/10 bg-[#f8f9f3] dark:border-white/10 dark:bg-[#151913]">
                <DialogTitle>Add Player</DialogTitle>
                <DialogDescription>Add a participant to this tournament.</DialogDescription>

                <form className="space-y-4" onSubmit={submitAddPlayer}>
                    <div className="grid gap-2">
                        <Label htmlFor="player_name">Player Name</Label>
                        <Input
                            id="player_name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            placeholder="Player name"
                            disabled={processing}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="checked_in"
                            checked={data.checked_in}
                            onCheckedChange={(checked) => setData('checked_in', Boolean(checked))}
                            disabled={processing}
                        />
                        <Label htmlFor="checked_in">Checked in</Label>
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                variant="outline"
                                className="rounded-full border-green-800/15 text-green-800 hover:bg-lime-300/20 dark:border-lime-300/20 dark:text-lime-300 dark:hover:bg-lime-300/10"
                                onClick={closeModal}
                                type="button"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-lime-400 font-semibold text-[#10140c] hover:bg-lime-300"
                        >
                            Save player
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

interface BulkAddPlayerModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tournamentOpenId: string;
}

export function BulkAddPlayerModal({ open, onOpenChange, tournamentOpenId }: BulkAddPlayerModalProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<BulkPlayerModalFormData>({
        prefix: '',
        count: '2',
    });

    const closeModal = () => {
        onOpenChange(false);
        clearErrors();
        reset();
    };

    const submitBulkAddPlayer = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post(route('tournament.player.bulk.store', tournamentOpenId), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    closeModal();
                    return;
                }

                onOpenChange(true);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    className="rounded-full border-green-800/15 bg-lime-300/15 font-semibold text-green-800 hover:border-lime-500/40 hover:bg-lime-300/30 dark:border-lime-300/20 dark:bg-lime-300/10 dark:text-lime-300 dark:hover:bg-lime-300/20"
                >
                    Bulk add
                </Button>
            </DialogTrigger>
            <DialogContent className="rounded-2xl border-black/10 bg-[#f8f9f3] dark:border-white/10 dark:bg-[#151913]">
                <DialogTitle>Bulk Add Players</DialogTitle>
                <DialogDescription>Create players from a shared name prefix and count.</DialogDescription>

                <form className="space-y-4" onSubmit={submitBulkAddPlayer}>
                    <div className="grid gap-2">
                        <Label htmlFor="bulk_player_prefix">Name Prefix</Label>
                        <Input
                            id="bulk_player_prefix"
                            value={data.prefix}
                            onChange={(event) => setData('prefix', event.target.value)}
                            placeholder="Player"
                            disabled={processing}
                            required
                        />
                        <InputError message={errors.prefix} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="bulk_player_count">Player Count</Label>
                        <Input
                            id="bulk_player_count"
                            type="number"
                            min={1}
                            value={data.count}
                            onChange={(event) => setData('count', event.target.value)}
                            placeholder="8"
                            disabled={processing}
                            required
                        />
                        <InputError message={errors.count} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                variant="outline"
                                className="rounded-full border-green-800/15 text-green-800 hover:bg-lime-300/20 dark:border-lime-300/20 dark:text-lime-300 dark:hover:bg-lime-300/10"
                                onClick={closeModal}
                                type="button"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-lime-400 font-semibold text-[#10140c] hover:bg-lime-300"
                        >
                            Create players
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

interface EditPlayerModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tournamentOpenId: string;
    player: PlayerSummary | null;
}

export function EditPlayerModal({ open, onOpenChange, tournamentOpenId, player }: EditPlayerModalProps) {
    const { data, setData, put, processing, errors, reset, clearErrors } = useForm<PlayerModalFormData>({
        name: '',
        checked_in: false,
    });

    useEffect(() => {
        if (!open || !player) {
            return;
        }

        setData({
            name: player.name,
            checked_in: player.checked_in,
        });
    }, [open, player, setData]);

    const closeModal = () => {
        onOpenChange(false);
        clearErrors();
        reset();
    };

    const submitEditPlayer = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!player) {
            return;
        }

        put(route('tournament.player.update', [tournamentOpenId, player.id]), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    closeModal();
                    return;
                }

                onOpenChange(true);
            }}
        >
            <DialogContent className="rounded-2xl border-black/10 bg-[#f8f9f3] dark:border-white/10 dark:bg-[#151913]">
                <DialogTitle>Edit Player</DialogTitle>
                <DialogDescription>Update player details.</DialogDescription>

                <form className="space-y-4" onSubmit={submitEditPlayer}>
                    <div className="grid gap-2">
                        <Label htmlFor="edit_player_name">Player Name</Label>
                        <Input
                            id="edit_player_name"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            placeholder="Player name"
                            disabled={processing}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="edit_player_checked_in"
                            checked={data.checked_in}
                            onCheckedChange={(checked) => setData('checked_in', Boolean(checked))}
                            disabled={processing}
                        />
                        <Label htmlFor="edit_player_checked_in">Checked in</Label>
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                variant="outline"
                                className="rounded-full border-green-800/15 text-green-800 hover:bg-lime-300/20 dark:border-lime-300/20 dark:text-lime-300 dark:hover:bg-lime-300/10"
                                onClick={closeModal}
                                type="button"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="rounded-full bg-lime-400 font-semibold text-[#10140c] hover:bg-lime-300"
                        >
                            Save changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

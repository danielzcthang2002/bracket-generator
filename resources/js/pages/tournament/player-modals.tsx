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
                <Button>Add player</Button>
            </DialogTrigger>
            <DialogContent>
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
                            <Button variant="outline" onClick={closeModal} type="button">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Save player
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
            <DialogContent>
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
                            <Button variant="outline" onClick={closeModal} type="button">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Save changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

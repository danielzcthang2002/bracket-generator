import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo } from 'react';

interface MatchScoreModalPlayer {
    id: number;
    name: string;
}

interface MatchScoreModalMatch {
    id: number;
    player1_id: number | null;
    player2_id: number | null;
    winner_id: number | null;
}

interface MatchScoreModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tournamentOpenId: string;
    players: MatchScoreModalPlayer[];
    match: MatchScoreModalMatch | null;
}

interface MatchScoreFormData {
    [key: string]: string | number | null;
    scores_csv: string;
    winner_id: string;
}

export function MatchScoreModal({ open, onOpenChange, tournamentOpenId, players, match }: MatchScoreModalProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<MatchScoreFormData>({
        scores_csv: '',
        winner_id: '',
    });

    const playerById = useMemo(() => {
        return players.reduce<Record<number, MatchScoreModalPlayer>>((accumulator, player) => {
            accumulator[player.id] = player;
            return accumulator;
        }, {});
    }, [players]);

    const selectablePlayers = useMemo(() => {
        if (!match) {
            return [];
        }

        return [match.player1_id, match.player2_id]
            .filter((playerId): playerId is number => typeof playerId === 'number')
            .map((playerId) => ({
                id: playerId,
                name: playerById[playerId]?.name ?? `Player #${playerId}`,
            }));
    }, [match, playerById]);

    useEffect(() => {
        if (!open || !match) {
            return;
        }

        setData({
            scores_csv: '',
            winner_id: match.winner_id ? String(match.winner_id) : '',
        });
        clearErrors();
    }, [clearErrors, match, open, setData]);

    const closeModal = () => {
        onOpenChange(false);
        clearErrors();
        reset();
    };

    const submitScores = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!match) {
            return;
        }

        post(route('tournament.match.score.update', [tournamentOpenId, match.id]), {
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
                <DialogTitle>Add Scores</DialogTitle>
                <DialogDescription>
                    Enter set scores as CSV (example: 6-4,4-6,7-5), then optionally select a winner.
                </DialogDescription>

                <form className="space-y-4" onSubmit={submitScores}>
                    <div className="grid gap-2">
                        <Label htmlFor="match_scores_csv">Scores CSV</Label>
                        <Input
                            id="match_scores_csv"
                            value={data.scores_csv}
                            onChange={(event) => setData('scores_csv', event.target.value)}
                            placeholder="6-4,4-6,7-5"
                            disabled={processing}
                            required
                        />
                        <InputError message={errors.scores_csv} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="match_winner_id">Winner</Label>
                        <select
                            id="match_winner_id"
                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                            value={data.winner_id}
                            onChange={(event) => setData('winner_id', event.target.value)}
                            disabled={processing || selectablePlayers.length === 0}
                        >
                            <option value="">Select winner (optional)</option>
                            {selectablePlayers.map((player) => (
                                <option key={player.id} value={player.id}>
                                    {player.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.winner_id} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline" type="button" onClick={closeModal} disabled={processing}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing || !match}>
                            Save scores
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

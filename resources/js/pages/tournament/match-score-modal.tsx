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
    participants?: MatchScoreModalParticipant[];
}

interface MatchScoreModalParticipantPlayer {
    id: number;
    name: string;
}

interface MatchScoreModalParticipant {
    id: number;
    player_id: number | null;
    position: number | null;
    score: string | null;
    rank: number | null;
    is_winner: boolean;
    player: MatchScoreModalParticipantPlayer | null;
}

interface MatchScoreModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tournamentOpenId: string;
    modeType: string | null;
    players: MatchScoreModalPlayer[];
    match: MatchScoreModalMatch | null;
}

interface MatchScoreFormData {
    [key: string]: string | number | null | Array<{ id: number; rank: string; score: string }>;
    scores_csv: string;
    winner_id: string;
    participants: Array<{
        id: number;
        rank: string;
        score: string;
    }>;
}

export function MatchScoreModal({ open, onOpenChange, tournamentOpenId, modeType, players, match }: MatchScoreModalProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<MatchScoreFormData>({
        scores_csv: '',
        winner_id: '',
        participants: [],
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

        const sortedParticipants = [...(match.participants ?? [])].sort((left, right) => {
            const leftPosition = left.position ?? Number.MAX_SAFE_INTEGER;
            const rightPosition = right.position ?? Number.MAX_SAFE_INTEGER;

            if (leftPosition !== rightPosition) {
                return leftPosition - rightPosition;
            }

            return left.id - right.id;
        });

        setData({
            scores_csv: '',
            winner_id: match.winner_id ? String(match.winner_id) : '',
            participants: sortedParticipants.map((participant) => ({
                id: participant.id,
                rank: participant.rank !== null ? String(participant.rank) : '',
                score: participant.score ?? '',
            })),
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

        const routeName = modeType === 'free_for_all' ? 'tournament.match.score.update-ffa' : 'tournament.match.score.update';

        post(route(routeName, [tournamentOpenId, match.id]), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    const isFreeForAll = modeType === 'free_for_all';
    const freeForAllParticipants = match?.participants ?? [];

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
                    {isFreeForAll
                        ? 'Rank each participant in finish order and optionally enter a score for each row.'
                        : 'Enter set scores as CSV (example: 6-4,4-6,7-5), then optionally select a winner or mark the match as a tie.'}
                </DialogDescription>

                <form className="space-y-4" onSubmit={submitScores}>
                    {isFreeForAll ? (
                        <div className="space-y-3">
                            {freeForAllParticipants.map((participant, index) => (
                                <div key={participant.id} className="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                                    <div className="space-y-1">
                                        <p className="text-sm font-medium">{participant.player?.name ?? `Player #${participant.player_id ?? participant.id}`}</p>
                                        <p className="text-muted-foreground text-xs">Position {participant.position ?? index + 1}</p>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor={`participant_rank_${participant.id}`}>Rank (optional)</Label>
                                            <Input
                                                id={`participant_rank_${participant.id}`}
                                                type="number"
                                                min={1}
                                                value={data.participants[index]?.rank ?? ''}
                                                onChange={(event) => {
                                                    setData('participants', data.participants.map((item, itemIndex) => (
                                                        itemIndex === index ? { ...item, rank: event.target.value } : item
                                                    )));
                                                }}
                                                disabled={processing}
                                            />
                                            <InputError message={errors[`participants.${index}.rank`]} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`participant_score_${participant.id}`}>Score</Label>
                                            <Input
                                                id={`participant_score_${participant.id}`}
                                                type="number"
                                                step="0.01"
                                                value={data.participants[index]?.score ?? ''}
                                                onChange={(event) => {
                                                    setData('participants', data.participants.map((item, itemIndex) => (
                                                        itemIndex === index ? { ...item, score: event.target.value } : item
                                                    )));
                                                }}
                                                disabled={processing}
                                                placeholder="Optional"
                                            />
                                            <InputError message={errors[`participants.${index}.score`]} />
                                        </div>
                                    </div>
                                </div>
                            ))}
                            <InputError message={errors.participants} />
                        </div>
                    ) : (
                        <>
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
                                    <option value="tie">Tie</option>
                                    {selectablePlayers.map((player) => (
                                        <option key={player.id} value={player.id}>
                                            {player.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.winner_id} />
                            </div>
                        </>
                    )}

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

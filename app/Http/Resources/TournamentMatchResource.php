<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentMatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_id' => $this->tournament_id,
            'state' => $this->state,
            'player1_id' => $this->player1_id,
            'player2_id' => $this->player2_id,
            'player1_score' => $this->player1_score,
            'player2_score' => $this->player2_score,
            'player1_prereq_match_id' => $this->player1_prereq_match_id,
            'player2_prereq_match_id' => $this->player2_prereq_match_id,
            'winner_id' => $this->winner_id,
            'loser_id' => $this->loser_id,
            'is_tie' => $this->is_tie,
            'round' => $this->round,
            'suggested_play_order' => $this->suggested_play_order,
            'identifier' => $this->suggested_play_order,
            'completed_at' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
            'player1_is_prereq_match_loser' => $this->player1_is_prereq_match_loser,
            'player2_is_prereq_match_loser' => $this->player2_is_prereq_match_loser,
            'scores_csv' => $this->whenLoaded('matchScores', function () {
                return $this->matchScores
                    ->groupBy('set')
                    ->sortKeys()
                    ->map(function ($setScores) {
                        $scores = $setScores->keyBy('player_id');
                        $p1 = $scores[$this->player1_id]->score ?? 0;
                        $p2 = $scores[$this->player2_id]->score ?? 0;
                        return "{$p1}-{$p2}";
                    })->implode(',');
            }),
            'player1' => new PlayerResource($this->whenLoaded('player1')),
            'player2' => new PlayerResource($this->whenLoaded('player2')),
        ];
    }
}

<?php

namespace App\Http\Resources\Tournament;

use App\Enums\TournamentModeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
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
            'url' => $this->open_id,
            'name' => $this->name,
            'tournament_type' => $this->mode_type,
            'description' => $this->description,
            'start_at' => $this->start_at ? $this->start_at : null,
            'end_at' => $this->end_at ? $this->end_at : null,
            'require_check_in' => $this->require_check_in,
            'check_in_time' => $this->check_in_time ? $this->check_in_time : null,
            'max_entry' => $this->max_entry,
            'status' => $this->status,
            'split_participant' => $this->when($this->mode_type === TournamentModeEnum::DOUBLE_ELIMINATION, $this->split_participant),
            'participants_per_match' => $this->whenNotNull($this->participants_per_match),
            'head_to_head_count' => $this->whenNotNull($this->head_to_head_count),
            'rank_by' => $this->whenNotNull($this->rank_by),
            'points_per_match_win' => $this->whenNotNull($this->points_per_match_win),
            'points_per_match_tie' => $this->whenNotNull($this->points_per_match_tie),
            'points_per_set_win' => $this->whenNotNull($this->points_per_set_win),
            'points_per_set_tie' => $this->whenNotNull($this->points_per_set_tie),
            'points_per_bye' => $this->whenNotNull($this->points_per_bye),
            'created_at' => $this->created_at,
        ];
    }
}

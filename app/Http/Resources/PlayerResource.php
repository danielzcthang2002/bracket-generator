<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
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
            'name' => $this->name,
            'tournament_id' => $this->tournament_id,
            'seed' => $this->seed,
            'checked_in' => $this->checked_in,
            'checked_in_at' => $this->checked_in_at ? $this->checked_in_at->toIso8601String() : null,
            'final_rank' => $this->final_rank,
        ];
    }
}

<?php

namespace App\Http\Resources\Tournament;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TournamentResourceCollection extends ResourceCollection
{
    public $collects = TournamentResource::class;
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}

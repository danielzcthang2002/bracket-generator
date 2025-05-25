<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\TournamentStoreRequest;
use App\Http\Resources\Tournament\TournamentResource;
use App\Http\Resources\Tournament\TournamentResourceCollection;
use App\Services\PlayerService;
use App\Services\TournamentService;
use Illuminate\Http\Request;

class TournamentApiController extends BaseApiController
{
    public function __construct(
        private readonly TournamentService $tournamentService,
        private readonly PlayerService $playerService
    ) {}

    public function show(Request $request, int $id)
    {
        $tournament = $this->tournamentService->getTournamentById($id);
        return $this->successResponse(
            new TournamentResource($tournament),
            'Tournament retrieved successfully'
        );
    }

    public function index(Request $request)
    {
        $tournaments = $this->tournamentService->getTournaments();
        return $this->successResponse(
            new TournamentResourceCollection($tournaments),
            'Tournament retrieved successfully'
        );
    }

    public function store(TournamentStoreRequest $request)
    {
        $validated = $request->validated();

        $newTournament = $this->tournamentService->createTournament($validated);

        return $this->successResponse(
            new TournamentResource($newTournament),
            'Tournament created successfully'
        );
    }

    public function update(TournamentStoreRequest $request, int $id){
        $validated = $request->validated();

        $updatedTournament = $this->tournamentService->updateTournament($id, $validated);

        return $this->successResponse(
            new TournamentResource($updatedTournament),
            'Tournament updated successfully'
        );
    }

    public function getPlayers(Request $request, int $tournamentId)
    {
        $players = $this->playerService->getPlayersByTournamentId($tournamentId);
        return $this->successResponse(
            $players,
            'Players retrieved successfully'
        );
    }

    public function addPlayer(Request $request, int $tournamentId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'checked_in' => 'boolean',
            'checked_in_at' => 'nullable|date',
        ]);

        $validated['tournament_id'] = $tournamentId;

        $newPlayer = $this->playerService->createPlayer($validated);

        return $this->successResponse(
            $newPlayer,
            'Player added successfully'
        );
    }

    public function removePlayer(Request $request, int $playerId)
    {
        $deletedPlayer = $this->playerService->removePlayer($playerId);
        return $this->successResponse(
            $deletedPlayer,
            'Player removed successfully'
        );
    }
}

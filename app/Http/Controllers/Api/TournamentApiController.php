<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\TournamentStoreRequest;
use App\Http\Resources\PlayerResource;
use App\Http\Resources\Tournament\TournamentResource;
use App\Http\Resources\Tournament\TournamentResourceCollection;
use App\Http\Resources\TournamentMatchResource;
use App\Services\PlayerService;
use App\Services\TournamentMatchService;
use App\Services\TournamentService;
use Illuminate\Http\Request;

class TournamentApiController extends BaseApiController
{
    public function __construct(
        private readonly TournamentService $tournamentService,
        private readonly PlayerService $playerService,
        private readonly TournamentMatchService $tournamentMatchService
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


        return response()->json([
            'status' => 'success',
            'message' => 'Tournament created successfully',
            'tournament' => new TournamentResource($newTournament),
        ], 201);
    }

    public function update(TournamentStoreRequest $request, int $id)
    {
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

        return response()->json([
            'status' => 'success',
            'message' => 'Player added successfully',
            'participant' => $newPlayer,
        ], 201);
    }

    public function removePlayer(Request $request)
    {
        $playerId = (int) $request->route('playerId');
        $deletedPlayer = $this->playerService->removePlayer($playerId);
        return $this->successResponse(
            $deletedPlayer,
            'Player removed successfully'
        );
    }

    public function checkInPlayer(Request $request, int $tournamentId, int $playerId)
    {
        $checkedIn = $this->playerService->checkInPlayer($tournamentId, $playerId);
        return $this->successResponse(
            $checkedIn,
            'Player checked in successfully'
        );
    }

    public function undoCheckInPlayer(Request $request, int $tournamentId, int $playerId)
    {
        $checkedIn = $this->playerService->undoCheckInPlayer($tournamentId, $playerId);
        return $this->successResponse(
            $checkedIn,
            'Player check-in undone successfully'
        );
    }

    public function importPlayers(Request $request, int $tournamentId, int $playerCount)
    {
        for ($i = 0; $i < $playerCount; $i++) {
            $data = [
                'name' => 'Player Test ' . ($i + 1),
                'checked_in' => true,
                'tournament_id' => $tournamentId,
            ];

            $this->playerService->createPlayer($data);
        }
        return $this->successResponse(
            'Players imported successfully'
        );
    }

    public function seedingStateMatches(Request $request, int $tournamentId)
    {
        $matches = $this->tournamentService->generateMatches($tournamentId)->groupBy('round');

        return response()->json([
            'status' => 'success',
            'message' => 'Matches generated successfully',
            'matches_by_round' => $matches,
        ], 200);
    }

    public function getMatches(Request $request, int $tournamentId)
    {
        $matches = $this->tournamentService->generateMatches($tournamentId);
        $data = $matches->map(function ($match) {
            return [
                'match' => new TournamentMatchResource($match)
            ];
        });
        return response()->json($data, 200);
    }

    public function getParticipants(Request $request, int $tournamentId)
    {
        $participants = $this->playerService->getPlayersByTournamentId($tournamentId);

        $data = $participants->map(function ($participant) {
            return [
                'participant' => new PlayerResource($participant),
            ];
        });
        return response()->json($data, 200);
    }

    public function processCheckedInPlayers(Request $request, int $tournamentId)
    {
        $this->playerService->processCheckedin($tournamentId);
        return $this->successResponse(
            'Checked-in players processed successfully'
        );
    }

    public function startTournament(Request $request, int $tournamentId)
    {
        $tournament = $this->tournamentService->startTournament($tournamentId);
        return $this->successResponse(
            new TournamentResource($tournament),
            'Tournament started successfully'
        );
    }

    public function endTournament(Request $request, int $tournamentId)
    {
        $tournament = $this->tournamentService->endTournament($tournamentId);
        return $this->successResponse(
            new TournamentResource($tournament),
            'Tournament ended successfully'
        );
    }

    public function updateMatchScores(Request $request)
    {
        $matchId = (int) $request->route('matchId');
        $data = $request->input('match');
        $scoresCsv = $data['scores_csv'] ?? '';
        $winnerId = $data['winner_id'] ?? null;
        $match = $this->tournamentMatchService->updateMatchScores($matchId, $scoresCsv, $winnerId);
        return $this->successResponse(
            new TournamentMatchResource($match),
            'Match scores updated successfully'
        );
    }
}

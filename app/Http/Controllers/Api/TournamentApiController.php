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

    public function show(Request $request, $openId)
    {
        $tournament = $this->tournamentService->getTournamentById($openId);
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

    public function update(TournamentStoreRequest $request, string $openId)
    {
        $validated = $request->validated();

        $updatedTournament = $this->tournamentService->updateTournament($openId, $validated);

        return $this->successResponse(
            new TournamentResource($updatedTournament),
            'Tournament updated successfully'
        );
    }

    public function getPlayers(Request $request, string $openId)
    {
        $players = $this->playerService->getPlayersByTournamentId($openId);
        return $this->successResponse(
            $players,
            'Players retrieved successfully'
        );
    }

    public function addPlayer(Request $request, string $openId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'checked_in' => 'boolean',
            'checked_in_at' => 'nullable|date',
        ]);


        $newPlayer = $this->playerService->createPlayer($validated, $openId);

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

    public function checkInPlayer(Request $request, string $openId, int $playerId)
    {
        $checkedIn = $this->playerService->checkInPlayer($openId, $playerId);
        return $this->successResponse(
            $checkedIn,
            'Player checked in successfully'
        );
    }

    public function undoCheckInPlayer(Request $request, string $openId, int $playerId)
    {
        $checkedIn = $this->playerService->undoCheckInPlayer($openId, $playerId);
        return $this->successResponse(
            $checkedIn,
            'Player check-in undone successfully'
        );
    }

    public function importPlayers(Request $request, string $openId, int $playerCount)
    {
        for ($i = 0; $i < $playerCount; $i++) {
            $data = [
                'name' => 'Player Test ' . ($i + 1),
                'checked_in' => true,
            ];

            $this->playerService->createPlayer($data, $openId);
        }
        return $this->successResponse(
            'Players imported successfully'
        );
    }

    public function seedingStateMatches(Request $request, string $openId)
    {
        $matches = $this->tournamentService->generateMatches($openId)->groupBy('round');
        $data = [];
        foreach ($matches as $key => $round) {
            $data[$key] = $round->map(function ($match) {
                return new TournamentMatchResource($match);
            });
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Matches generated successfully',
            'matches_by_round' => $data,
        ], 200);
    }

    public function getMatches(Request $request, string $openId)
    {
        $matches = $this->tournamentService->generateMatches($openId);
        $data = $matches->map(function ($match) {
            return [
                'match' => new TournamentMatchResource($match)
            ];
        });
        return response()->json($data, 200);
    }

    public function getParticipants(Request $request, string $openId)
    {
        $participants = $this->playerService->getPlayersByTournamentId($openId);

        $data = $participants->map(function ($participant) {
            return [
                'participant' => new PlayerResource($participant),
            ];
        });
        return response()->json($data, 200);
    }

    public function processCheckedInPlayers(Request $request, string $openId)
    {
        $this->playerService->processCheckedin($openId);
        return $this->successResponse(
            'Checked-in players processed successfully'
        );
    }

    public function startTournament(Request $request, string $openId)
    {
        $tournament = $this->tournamentService->startTournament($openId);
        return $this->successResponse(
            new TournamentResource($tournament),
            'Tournament started successfully'
        );
    }

    public function endTournament(Request $request, string $openId)
    {
        $tournament = $this->tournamentService->endTournament($openId);
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

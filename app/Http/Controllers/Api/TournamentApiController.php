<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Bracket\Enums\DtTournamentModeEnum;
use App\Bracket\Enums\DtTournamentStatus;
use App\Http\Requests\Tournament\TournamentStoreRequest;
use App\Http\Resources\PlayerResource;
use App\Http\Resources\Tournament\TournamentResource;
use App\Http\Resources\TournamentMatchResource;
use App\Bracket\Models\DtTournament;
use App\Bracket\Contracts\DtModeResolver;
use App\Bracket\Services\DtPlayerService;
use App\Bracket\Services\DtTournamentMatchService;
use App\Bracket\Services\DtTournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentApiController extends BaseApiController
{
    public function __construct(
        private readonly DtTournamentService $tournamentService,
        private readonly DtPlayerService $playerService,
        private readonly DtTournamentMatchService $tournamentMatchService,
        private readonly DtModeResolver $modeResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tournaments = DtTournament::query()
            ->withCount(['players', 'matches'])
            ->latest()
            ->get();

        return $this->successResponse(
            TournamentResource::collection($tournaments),
            'Tournaments retrieved successfully.',
            meta: ['count' => $tournaments->count()],
        );
    }

    public function show(Request $request, string $openId): JsonResponse
    {
        $tournament = DtTournament::query()
            ->withCount(['players', 'matches'])
            ->where('open_id', $openId)
            ->firstOrFail();

        return $this->successResponse(new TournamentResource($tournament), 'Tournament retrieved successfully.');
    }

    public function store(TournamentStoreRequest $request): JsonResponse
    {
        $tournament = $this->tournamentService->createTournament($request->validated());

        return $this->successResponse(new TournamentResource($tournament), 'Tournament created successfully.', 201);
    }

    public function update(TournamentStoreRequest $request, string $openId): JsonResponse
    {
        $tournament = $this->tournamentService->updateTournament($openId, $request->validated());

        return $this->successResponse(new TournamentResource($tournament), 'Tournament updated successfully.');
    }

    public function getParticipants(Request $request, string $openId): JsonResponse
    {
        $participants = $this->playerService->getPlayersByTournamentId($openId);

        return $this->successResponse(
            PlayerResource::collection($participants),
            'Participants retrieved successfully.',
            meta: ['count' => $participants->count()],
        );
    }

    public function addPlayer(Request $request, string $openId): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'checked_in' => ['sometimes', 'boolean'],
            'checked_in_at' => ['nullable', 'date'],
        ]);

        $participant = $this->playerService->createPlayer($validated, $openId);

        return $this->successResponse(new PlayerResource($participant), 'Participant created successfully.', 201);
    }

    public function removePlayer(Request $request, string $openId, int $playerId): JsonResponse
    {
        $tournament = $this->findTournament($openId);
        if (in_array($tournament->status, [DtTournamentStatus::STARTED, DtTournamentStatus::ENDED], true)) {
            return $this->errorResponse('Players cannot be deleted after a tournament has started.', 409);
        }
        $tournament->players()->whereKey($playerId)->firstOrFail();
        $this->playerService->removePlayer($playerId);

        return response()->json(null, 204);
    }

    public function removePlayers(Request $request, string $openId): JsonResponse
    {
        $tournament = $this->findTournament($openId);
        if (in_array($tournament->status, [DtTournamentStatus::STARTED, DtTournamentStatus::ENDED], true)) {
            return $this->errorResponse('Players cannot be deleted after a tournament has started.', 409);
        }

        $validated = $request->validate([
            'player_ids' => ['required', 'array', 'min:1'],
            'player_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $deletedCount = $this->playerService->removePlayers($openId, $validated['player_ids']);

        return $this->successResponse(
            ['deleted_count' => $deletedCount],
            'Participants deleted successfully.',
        );
    }

    public function updatePlayerCheckIn(Request $request, string $openId, int $playerId): JsonResponse
    {
        $validated = $request->validate(['checked_in' => ['required', 'boolean']]);
        $participant = $validated['checked_in']
            ? $this->playerService->checkInPlayer($openId, $playerId)
            : $this->playerService->undoCheckInPlayer($openId, $playerId);

        return $this->successResponse(new PlayerResource($participant), 'Participant check-in updated successfully.');
    }

    public function processCheckedInPlayers(Request $request, string $openId): JsonResponse
    {
        $this->findTournament($openId);
        $this->playerService->processCheckedin($openId);

        return $this->successResponse(null, 'Unchecked participants removed and seeds updated.');
    }

    public function getMatches(Request $request, string $openId): JsonResponse
    {
        $tournament = $this->findTournament($openId);
        $matches = $tournament->matches()
            ->with(['matchScores', 'player1', 'player2', 'participants.player'])
            ->orderBy('suggested_play_order')
            ->get();

        return $this->successResponse(
            TournamentMatchResource::collection($matches),
            'Matches retrieved successfully.',
            meta: ['count' => $matches->count()],
        );
    }

    public function previewMatches(Request $request, string $openId): JsonResponse
    {
        $tournament = $this->findTournament($openId);
        $preview = ! in_array($tournament->status, [DtTournamentStatus::STARTED, DtTournamentStatus::ENDED], true)
            ? $this->modeResolver->resolve($tournament->mode_type->value)->previewBracket($tournament)
            : [];

        return $this->successResponse(
            $preview,
            'Match preview retrieved successfully.',
            meta: ['count' => collect($preview)->flatten(1)->count()],
        );
    }

    public function generateMatches(Request $request, string $openId): JsonResponse
    {
        $matches = $this->tournamentService->generateMatches($openId)->load(['matchScores', 'player1', 'player2', 'participants.player']);

        return $this->successResponse(
            TournamentMatchResource::collection($matches),
            'Matches generated successfully.',
            201,
            ['count' => $matches->count()],
        );
    }

    public function startTournament(Request $request, string $openId): JsonResponse
    {
        $tournament = $this->tournamentService->startTournament($openId);

        return $this->successResponse(new TournamentResource($tournament), 'Tournament started successfully.');
    }

    public function endTournament(Request $request, string $openId): JsonResponse
    {
        $tournament = $this->tournamentService->endTournament($openId);

        return $this->successResponse(new TournamentResource($tournament), 'Tournament ended successfully.');
    }

    public function updateMatchScores(Request $request, string $openId, int $matchId): JsonResponse
    {
        $tournament = $this->findTournament($openId);
        $match = $tournament->matches()->whereKey($matchId)->firstOrFail();
        $allowsTie = in_array($tournament->mode_type, [DtTournamentModeEnum::ROUND_ROBIN, DtTournamentModeEnum::SWISS], true);
        $allowedWinners = array_values(array_filter([$match->player1_id, $match->player2_id]));

        $validated = $request->validate([
            'scores_csv' => ['required', 'string'],
            'winner_id' => [
                'nullable',
                Rule::in(array_merge($allowedWinners, $allowsTie ? ['tie'] : [])),
            ],
        ]);

        $updatedMatch = $this->tournamentMatchService->updateMatchScores(
            $match->id,
            $validated['scores_csv'],
            $validated['winner_id'] ?? null,
        );

        return $this->successResponse(new TournamentMatchResource($updatedMatch->load(['matchScores', 'player1', 'player2'])), 'Match score updated successfully.');
    }

    private function findTournament(string $openId): DtTournament
    {
        return DtTournament::query()->where('open_id', $openId)->firstOrFail();
    }
}

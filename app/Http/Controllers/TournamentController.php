<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Services\TournamentMatchService;
use App\Services\TournamentService;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function index(Request $request): Response
    {
        $tournaments = Tournament::query()
            ->withCount(['players', 'matches'])
            ->latest()
            ->get()
            ->map(function (Tournament $tournament): array {
                return [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'open_id' => $tournament->open_id,
                    'mode_type' => $tournament->mode_type?->value,
                    'status' => $tournament->status?->value,
                    'start_at' => $tournament->start_at?->toIso8601String(),
                    'end_at' => $tournament->end_at?->toIso8601String(),
                    'players_count' => $tournament->players_count,
                    'matches_count' => $tournament->matches_count,
                    'created_at' => $tournament->created_at?->toIso8601String(),
                ];
            })
            ->values();

        return Inertia::render('tournament/tournament-index', [
            'tournaments' => $tournaments,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tournament/tournament-create', [
            'modeOptions' => TournamentModeEnum::options(),
            'statusOptions' => TournamentStatus::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTournament($request);

        $tournament = Tournament::query()->create([
            'name' => $validated['name'],
            'open_id' => Tournament::generateOpenId(),
            'mode_type' => $validated['mode_type'],
            'description' => $validated['description'] ?? null,
            'start_at' => $validated['start_at'] ?? null,
            'end_at' => $validated['end_at'] ?? null,
            'require_check_in' => $validated['require_check_in'] ?? false,
            'check_in_time' => $validated['check_in_time'] ?? null,
            'max_entry' => $validated['max_entry'] ?? null,
            'status' => $validated['status'],
            'split_participant' => $validated['split_participant'] ?? false,
            'participants_per_match' => $validated['participants_per_match'] ?? null,
            'head_to_head_count' => $validated['head_to_head_count'] ?? null,
            'rank_by' => $validated['rank_by'] ?? null,
            'points_per_match_win' => $validated['points_per_match_win'] ?? null,
            'points_per_match_tie' => $validated['points_per_match_tie'] ?? null,
            'points_per_set_win' => $validated['points_per_set_win'] ?? null,
            'points_per_set_tie' => $validated['points_per_set_tie'] ?? null,
            'points_per_bye' => $validated['points_per_bye'] ?? null,
        ]);

        return redirect()
            ->route('tournament.show', $tournament->open_id)
            ->with('success', 'Tournament created successfully.');
    }

    public function show(string $openId): Response
    {
        $tournament = Tournament::query()
            ->withCount(['players', 'matches'])
            ->with(['players', 'matches'])
            ->where('open_id', $openId)
            ->firstOrFail();

        return Inertia::render('tournament/tournament-show', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'open_id' => $tournament->open_id,
                'description' => $tournament->description,
                'mode_type' => $tournament->mode_type?->value,
                'status' => $tournament->status?->value,
                'start_at' => $tournament->start_at?->toIso8601String(),
                'end_at' => $tournament->end_at?->toIso8601String(),
                'require_check_in' => $tournament->require_check_in,
                'check_in_time' => $tournament->check_in_time,
                'max_entry' => $tournament->max_entry,
                'split_participant' => $tournament->split_participant,
                'participants_per_match' => $tournament->participants_per_match,
                'head_to_head_count' => $tournament->head_to_head_count,
                'rank_by' => $tournament->rank_by,
                'players_count' => $tournament->players_count,
                'matches_count' => $tournament->matches_count,
                'players' => $tournament->players->map(function ($player): array {
                    return [
                        'id' => $player->id,
                        'name' => $player->name,
                        'checked_in' => (bool) $player->checked_in,
                        'seed' => $player->seed,
                    ];
                })->values(),
                'matches' => $tournament->matches->map(function ($match): array {
                    return [
                        'id' => $match->id,
                        'state' => $match->state?->value,
                        'round' => $match->round,
                        'suggested_play_order' => $match->suggested_play_order,
                        'player1_id' => $match->player1_id,
                        'player2_id' => $match->player2_id,
                        'player1_prereq_match_id' => $match->player1_prereq_match_id,
                        'player2_prereq_match_id' => $match->player2_prereq_match_id,
                        'player1_is_prereq_match_loser' => (bool) $match->player1_is_prereq_match_loser,
                        'player2_is_prereq_match_loser' => (bool) $match->player2_is_prereq_match_loser,
                        'player1_score' => $match->player1_score,
                        'player2_score' => $match->player2_score,
                        'winner_id' => $match->winner_id,
                    ];
                })->values(),
            ],
        ]);
    }

    public function edit(string $openId): Response
    {
        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();

        return Inertia::render('tournament/tournament-edit', [
            'modeOptions' => TournamentModeEnum::options(),
            'statusOptions' => TournamentStatus::options(),
            'tournament' => [
                'name' => $tournament->name,
                'open_id' => $tournament->open_id,
                'mode_type' => $tournament->mode_type?->value,
                'description' => $tournament->description,
                'start_at' => $tournament->start_at?->format('Y-m-d\\TH:i'),
                'end_at' => $tournament->end_at?->format('Y-m-d\\TH:i'),
                'require_check_in' => (bool) $tournament->require_check_in,
                'check_in_time' => $tournament->check_in_time,
                'max_entry' => $tournament->max_entry,
                'status' => $tournament->status?->value,
                'split_participant' => (bool) $tournament->split_participant,
                'participants_per_match' => $tournament->participants_per_match,
                'head_to_head_count' => $tournament->head_to_head_count,
                'rank_by' => $tournament->rank_by,
                'points_per_match_win' => $tournament->points_per_match_win,
                'points_per_match_tie' => $tournament->points_per_match_tie,
                'points_per_set_win' => $tournament->points_per_set_win,
                'points_per_set_tie' => $tournament->points_per_set_tie,
                'points_per_bye' => $tournament->points_per_bye,
            ],
        ]);
    }

    public function update(Request $request, string $openId): RedirectResponse
    {
        $validated = $this->validateTournament($request);

        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();
        $tournament->update($validated);

        return redirect()
            ->route('tournament.show', $tournament->open_id)
            ->with('success', 'Tournament updated successfully.');
    }

    private function validateTournament(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mode_type' => ['required', Rule::in(TournamentModeEnum::options())],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'require_check_in' => ['sometimes', 'boolean'],
            'check_in_time' => ['nullable', 'integer', 'min:1'],
            'max_entry' => ['nullable', 'integer', 'min:2'],
            'status' => ['required', Rule::in(TournamentStatus::options())],
            'split_participant' => ['sometimes', 'boolean'],
            'participants_per_match' => ['nullable', 'integer', 'min:2'],
            'head_to_head_count' => ['nullable', 'integer', 'min:1'],
            'rank_by' => ['nullable', 'string', 'max:50'],
            'points_per_match_win' => ['nullable', 'numeric', 'min:0'],
            'points_per_match_tie' => ['nullable', 'numeric', 'min:0'],
            'points_per_set_win' => ['nullable', 'numeric', 'min:0'],
            'points_per_set_tie' => ['nullable', 'numeric', 'min:0'],
            'points_per_bye' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    public function storePlayer(Request $request, string $openId): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'checked_in' => ['sometimes', 'boolean'],
        ]);

        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();

        Player::query()->create([
            'name' => $validated['name'],
            'tournament_id' => $tournament->id,
            'checked_in' => $validated['checked_in'] ?? false,
            'checked_in_at' => ($validated['checked_in'] ?? false) ? now() : null,
        ]);

        return redirect()
            ->route('tournament.show', $openId)
            ->with('success', 'Player added successfully.');
    }

    public function updatePlayer(Request $request, string $openId, int $playerId): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'checked_in' => ['sometimes', 'boolean'],
        ]);

        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();

        $player = Player::query()
            ->where('id', $playerId)
            ->where('tournament_id', $tournament->id)
            ->firstOrFail();

        $isCheckedIn = $validated['checked_in'] ?? false;

        $player->update([
            'name' => $validated['name'],
            'checked_in' => $isCheckedIn,
            'checked_in_at' => $isCheckedIn ? ($player->checked_in_at ?? now()) : null,
        ]);

        return redirect()
            ->route('tournament.show', $openId)
            ->with('success', 'Player updated successfully.');
    }

    public function destroyPlayer(string $openId, int $playerId): RedirectResponse
    {
        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();

        Player::query()
            ->where('id', $playerId)
            ->where('tournament_id', $tournament->id)
            ->firstOrFail();

        Player::query()
            ->where('id', $playerId)
            ->where('tournament_id', $tournament->id)
            ->delete();

        return redirect()
            ->route('tournament.show', $openId)
            ->with('success', 'Player deleted successfully.');
    }

    public function startTournament(string $openId): RedirectResponse
    {
        $service = new TournamentService();

        $service->startTournament($openId);

        return redirect()
            ->route('tournament.show', $openId)
            ->with('success', 'Tournament started successfully.');
    }

    public function updateMatchScore(Request $request, string $openId, int $matchId): RedirectResponse
    {
        $validated = $request->validate([
            'scores_csv' => ['required', 'string'],
            'winner_id' => ['nullable', 'integer'],
        ]);

        $tournament = Tournament::query()->where('open_id', $openId)->firstOrFail();
        $match = $tournament->matches()->where('id', $matchId)->firstOrFail();

        $service = new TournamentMatchService();
        $service->updateMatchScores(
            $match->id,
            $validated['scores_csv'],
            $validated['winner_id'] ?? null,
        );

        return redirect()
            ->route('tournament.show', $openId)
            ->with('success', 'Match scores updated successfully.');
    }
}
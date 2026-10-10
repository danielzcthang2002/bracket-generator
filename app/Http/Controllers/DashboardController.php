<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TournamentMatchStateEnum;
use App\Enums\TournamentModeEnum;
use App\Enums\TournamentStatus;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $trendStart = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $tournamentsByMonth = Tournament::query()
            ->where('created_at', '>=', $trendStart)
            ->get(['created_at'])
            ->groupBy(fn (Tournament $tournament): string => $tournament->created_at->format('Y-m'));

        $totalMatches = TournamentMatch::query()->count();
        $completedMatches = TournamentMatch::query()->where('state', TournamentMatchStateEnum::COMPLETE->value)->count();
        $statusCounts = Tournament::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $modeCounts = Tournament::query()->selectRaw('mode_type, COUNT(*) as aggregate')->groupBy('mode_type')->pluck('aggregate', 'mode_type');

        $recentTournaments = Tournament::query()
            ->withCount(['players', 'matches'])
            ->withCount(['matches as completed_matches_count' => fn ($query) => $query->where('state', TournamentMatchStateEnum::COMPLETE->value)])
            ->latest()->limit(5)->get()
            ->map(fn (Tournament $tournament): array => [
                'id' => $tournament->id, 'name' => $tournament->name, 'open_id' => $tournament->open_id,
                'mode_type' => $tournament->mode_type?->value, 'status' => $tournament->status?->value,
                'start_at' => $tournament->start_at?->toIso8601String(), 'players_count' => $tournament->players_count,
                'matches_count' => $tournament->matches_count, 'completed_matches_count' => $tournament->completed_matches_count,
            ])->values();

        return Inertia::render('dashboard', [
            'stats' => [
                'tournaments' => Tournament::query()->count(), 'players' => Player::query()->count(),
                'completed_matches' => $completedMatches, 'total_matches' => $totalMatches,
                'active_tournaments' => Tournament::query()->whereIn('status', [
                    TournamentStatus::OPEN->value, TournamentStatus::SEEDING->value, TournamentStatus::STARTED->value,
                ])->count(),
            ],
            'tournamentTrend' => collect(range(0, 5))->map(function (int $offset) use ($trendStart, $tournamentsByMonth): array {
                $month = $trendStart->addMonths($offset);
                return ['label' => $month->format('M'), 'value' => $tournamentsByMonth->get($month->format('Y-m'), collect())->count()];
            }),
            'statusBreakdown' => collect(TournamentStatus::cases())->map(fn (TournamentStatus $status): array => [
                'label' => TournamentStatus::labels()[$status->value], 'value' => (int) ($statusCounts[$status->value] ?? 0), 'key' => $status->value,
            ])->values(),
            'modeBreakdown' => collect(TournamentModeEnum::cases())->map(fn (TournamentModeEnum $mode): array => [
                'label' => TournamentModeEnum::labels()[$mode->value], 'value' => (int) ($modeCounts[$mode->value] ?? 0),
            ])->values(),
            'recentTournaments' => $recentTournaments,
        ]);
    }
}

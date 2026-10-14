<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Bracket\Enums\DtTournamentMatchStateEnum;
use App\Bracket\Enums\DtTournamentModeEnum;
use App\Bracket\Enums\DtTournamentStatus;
use App\Bracket\Models\DtPlayer;
use App\Bracket\Models\DtTournament;
use App\Bracket\Models\DtTournamentMatch;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $trendStart = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $tournamentsByMonth = DtTournament::query()
            ->where('created_at', '>=', $trendStart)
            ->get(['created_at'])
            ->groupBy(fn (DtTournament $tournament): string => $tournament->created_at->format('Y-m'));

        $totalMatches = DtTournamentMatch::query()->count();
        $completedMatches = DtTournamentMatch::query()->where('state', DtTournamentMatchStateEnum::COMPLETE->value)->count();
        $statusCounts = DtTournament::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $modeCounts = DtTournament::query()->selectRaw('mode_type, COUNT(*) as aggregate')->groupBy('mode_type')->pluck('aggregate', 'mode_type');

        $recentTournaments = DtTournament::query()
            ->withCount(['players', 'matches'])
            ->withCount(['matches as completed_matches_count' => fn ($query) => $query->where('state', DtTournamentMatchStateEnum::COMPLETE->value)])
            ->latest()->limit(5)->get()
            ->map(fn (DtTournament $tournament): array => [
                'id' => $tournament->id, 'name' => $tournament->name, 'open_id' => $tournament->open_id,
                'mode_type' => $tournament->mode_type?->value, 'status' => $tournament->status?->value,
                'start_at' => $tournament->start_at?->toIso8601String(), 'players_count' => $tournament->players_count,
                'matches_count' => $tournament->matches_count, 'completed_matches_count' => $tournament->completed_matches_count,
            ])->values();

        return Inertia::render('dashboard', [
            'stats' => [
                'tournaments' => DtTournament::query()->count(), 'players' => DtPlayer::query()->count(),
                'completed_matches' => $completedMatches, 'total_matches' => $totalMatches,
                'active_tournaments' => DtTournament::query()->whereIn('status', [
                    DtTournamentStatus::OPEN->value, DtTournamentStatus::SEEDING->value, DtTournamentStatus::STARTED->value,
                ])->count(),
            ],
            'tournamentTrend' => collect(range(0, 5))->map(function (int $offset) use ($trendStart, $tournamentsByMonth): array {
                $month = $trendStart->addMonths($offset);
                return ['label' => $month->format('M'), 'value' => $tournamentsByMonth->get($month->format('Y-m'), collect())->count()];
            }),
            'statusBreakdown' => collect(DtTournamentStatus::cases())->map(fn (DtTournamentStatus $status): array => [
                'label' => DtTournamentStatus::labels()[$status->value], 'value' => (int) ($statusCounts[$status->value] ?? 0), 'key' => $status->value,
            ])->values(),
            'modeBreakdown' => collect(DtTournamentModeEnum::cases())->map(fn (DtTournamentModeEnum $mode): array => [
                'label' => DtTournamentModeEnum::labels()[$mode->value], 'value' => (int) ($modeCounts[$mode->value] ?? 0),
            ])->values(),
            'recentTournaments' => $recentTournaments,
        ]);
    }
}

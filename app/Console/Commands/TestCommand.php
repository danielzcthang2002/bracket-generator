<?php

namespace App\Console\Commands;

use App\Bracket\Services\DtTournamentMatchService;
use App\Bracket\Services\DtTournamentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = app(DtTournamentMatchService::class);

        $result =  $service->generateMatches(1);

        Log::info(json_encode($result));
    }
}

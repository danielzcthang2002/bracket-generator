<?php

namespace App\Console\Commands;

use App\Services\TournamentMatchService;
use App\Services\TournamentService;
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
        $service = new TournamentMatchService();

        $result =  $service->generateMatches(1);

        Log::info(json_encode($result));
    }
}

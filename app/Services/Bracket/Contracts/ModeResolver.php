<?php
namespace App\Services\Bracket\Contracts;

use App\Services\Bracket\DoubleEliminationService;
use App\Services\Bracket\FreeForAllService;
use App\Services\Bracket\RoundRobinService;
use App\Services\Bracket\SingleEliminationService;
use App\Services\Bracket\SwissService;

class ModeResolver
{
    public function resolve(string $mode): HasMode
    {
        $services = [
            'single_elimination' => SingleEliminationService::class,
            'double_elimination' => DoubleEliminationService::class,
            'round_robin' => RoundRobinService::class,
            'swiss' => SwissService::class,
            'free_for_all' => FreeForAllService::class,
        ];

        $serviceClass = $services[$mode]
            ?? throw new \InvalidArgumentException(
                "Unsupported tournament mode: {$mode}"
            );

        return app($serviceClass);
    }
}
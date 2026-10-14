<?php
namespace App\Bracket\Contracts;

use App\Bracket\Services\DtDoubleEliminationService;
use App\Bracket\Services\DtFreeForAllService;
use App\Bracket\Services\DtRoundRobinService;
use App\Bracket\Services\DtSingleEliminationService;
use App\Bracket\Services\DtSwissService;

class DtModeResolver
{
    public function resolve(string $mode): DtHasMode
    {
        $services = [
            'single_elimination' => DtSingleEliminationService::class,
            'double_elimination' => DtDoubleEliminationService::class,
            'round_robin' => DtRoundRobinService::class,
            'swiss' => DtSwissService::class,
            'free_for_all' => DtFreeForAllService::class,
        ];

        $serviceClass = $services[$mode]
            ?? throw new \InvalidArgumentException(
                "Unsupported tournament mode: {$mode}"
            );

        return app($serviceClass);
    }
}
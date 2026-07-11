<?php

namespace App\Enums;

enum TournamentModeEnum: string
{
    case SINGLE_ELIMINATION = 'single_elimination';
    case DOUBLE_ELIMINATION = 'double_elimination';
    case ROUND_ROBIN = 'round_robin';
    case SWISS = 'swiss';
    case FREE_FOR_ALL = 'free_for_all';


    public static function labels(): array
    {
        return [
            self::SINGLE_ELIMINATION->value => 'Single Elimination',
            self::DOUBLE_ELIMINATION->value => 'Double Elimination',
            self::ROUND_ROBIN->value => 'Round Robin',
            self::SWISS->value => 'Swiss',
            self::FREE_FOR_ALL->value => 'Free For All',
        ];
    }
    public static function options(): array
    {
        return array_map(
            fn(self $mode) => $mode->value,
            self::cases()
        );
    }
}

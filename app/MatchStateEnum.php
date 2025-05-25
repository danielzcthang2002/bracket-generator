<?php

namespace App;

enum MatchStateEnum: string
{
    case PENDING = 'pending';
    case OPEN = 'open';
    case CLOSED = 'close';
    case COMPLETE = 'complete';


    public static function labels(): array
    {
        return [
            self::PENDING->value => 'Pending',
            self::OPEN->value => 'Open',
            self::CLOSED->value => 'Closed',
            self::COMPLETE->value => 'Complete',
        ];
    }
    public static function options(): array
    {
        return array_map(
            fn(self $state) => $state->value,
            self::cases()
        );
    }
}

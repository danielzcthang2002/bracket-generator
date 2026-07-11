<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case PENDING = 'pending';
    case OPEN = 'open';
    case CLOSED = 'closed';
    case SEEDING = 'seeding';
    case STARTED = 'started';
    case ENDED = 'ended';

    public static function labels(): array
    {
        return [
            self::PENDING->value => 'Pending',
            self::OPEN->value => 'Open',
            self::CLOSED->value => 'Closed',
            self::SEEDING->value => 'Seeding',
            self::STARTED->value => 'Started',
            self::ENDED->value => 'Ended',
        ];
    }
    public static function options(): array
    {
        return array_map(
            fn(self $status) => $status->value,
            self::cases()
        );
    }
}

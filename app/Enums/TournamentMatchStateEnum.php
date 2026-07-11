<?php

namespace App\Enums;

enum TournamentMatchStateEnum:string
{
    case PENDING = 'pending';
    case OPEN = 'open';

    case CLOSE = 'close';
    case COMPLETE = 'complete';
}

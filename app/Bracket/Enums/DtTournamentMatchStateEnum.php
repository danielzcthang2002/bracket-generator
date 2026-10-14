<?php

namespace App\Bracket\Enums;

enum DtTournamentMatchStateEnum:string
{
    case PENDING = 'pending';
    case OPEN = 'open';

    case CLOSE = 'close';
    case COMPLETE = 'complete';
}

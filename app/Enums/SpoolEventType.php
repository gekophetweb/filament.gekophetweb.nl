<?php

namespace App\Enums;

enum SpoolEventType: string
{
    case Added = 'added';
    case Weighed = 'weighed';
    case Used = 'used';
    case Moved = 'moved';
    case Emptied = 'emptied';
}

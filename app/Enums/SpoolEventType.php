<?php

namespace App\Enums;

enum SpoolEventType: string
{
    case Added = 'added';
    case Opened = 'opened';
    case UsedUp = 'used_up';
    case Removed = 'removed';
    case Moved = 'moved';

    /** Older events from when spools were weighed; kept so their history still reads. */
    case Weighed = 'weighed';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'Added (boxed)',
            self::Opened => 'Put on spool',
            self::UsedUp => 'Used up',
            self::Removed => 'Removed',
            self::Moved => 'Moved',
            self::Weighed => 'Weighed',
            self::Used => 'Used',
        };
    }
}

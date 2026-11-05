<?php

namespace App\Enums;

enum SpoolStatus: string
{
    case Sealed = 'sealed';
    case InUse = 'in_use';
    case Empty = 'empty';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Sealed => 'Sealed',
            self::InUse => 'In use',
            self::Empty => 'Empty',
            self::Archived => 'Archived',
        };
    }
}

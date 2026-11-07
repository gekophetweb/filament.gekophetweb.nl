<?php

namespace App\Enums;

enum SpoolStatus: string
{
    case Boxed = 'boxed';
    case OnSpool = 'on_spool';
    case UsedUp = 'used_up';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Boxed => 'Boxed',
            self::OnSpool => 'On spool',
            self::UsedUp => 'Used up',
            self::Archived => 'Archived',
        };
    }

    /**
     * Statuses of spools that still hold filament.
     *
     * @return list<self>
     */
    public static function owned(): array
    {
        return [self::Boxed, self::OnSpool];
    }
}

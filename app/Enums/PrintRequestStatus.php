<?php

namespace App\Enums;

enum PrintRequestStatus: string
{
    case Open = 'open';
    case Printed = 'printed';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Printed => 'Printed',
            self::Declined => 'Declined',
        };
    }
}

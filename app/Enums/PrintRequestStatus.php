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

    /** Tailwind classes for the status badge, in the inbox and on the share link. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-amber-100 text-amber-900',
            self::Printed => 'bg-emerald-100 text-emerald-900',
            self::Declined => 'bg-stone-200 text-stone-700',
        };
    }
}

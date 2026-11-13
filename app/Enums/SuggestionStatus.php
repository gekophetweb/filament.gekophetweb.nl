<?php

namespace App\Enums;

enum SuggestionStatus: string
{
    case Open = 'open';
    case Planned = 'planned';
    case Done = 'done';
    case Declined = 'declined';

    /**
     * Statuses the owner still has to act on: new suggestions and planned ones not done yet.
     *
     * @return list<self>
     */
    public static function toHandle(): array
    {
        return [self::Open, self::Planned];
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Planned => 'Planned',
            self::Done => 'Done',
            self::Declined => 'Declined',
        };
    }

    /** Tailwind classes for the status badge, in the inbox and on the share link. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-amber-100 text-amber-900',
            self::Planned => 'bg-sky-100 text-sky-900',
            self::Done => 'bg-emerald-100 text-emerald-900',
            self::Declined => 'bg-stone-200 text-stone-700',
        };
    }
}

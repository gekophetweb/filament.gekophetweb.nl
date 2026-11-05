<?php

namespace App\Support;

final class Color
{
    /** @return array{0: float, 1: float, 2: float} hue 0-360, saturation 0-1, lightness 0-1 */
    public static function hsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn (int $c): float => $c / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0.0) {
            return [0.0, 0.0, $l];
        }

        $s = $d / (1 - abs(2 * $l - 1));
        $h = match ($max) {
            $r => 60 * fmod(($g - $b) / $d, 6),
            $g => 60 * (($b - $r) / $d + 2),
            default => 60 * (($r - $g) / $d + 4),
        };

        return [$h < 0 ? $h + 360 : $h, $s, $l];
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $hex): array
    {
        $hex = self::normalize($hex) ?? '#000000';

        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    /** "#abc", "aabbcc" or "#AABBCC" -> "#AABBCC"; null when invalid. */
    public static function normalize(?string $hex): ?string
    {
        $hex = ltrim(trim((string) $hex), '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#'.strtoupper($hex) : null;
    }

    /** Bucket a hex color into a named family for filtering. */
    public static function family(string $hex): string
    {
        [$h, $s, $l] = self::hsl($hex);

        return match (true) {
            $l <= 0.12 => 'black',
            $l >= 0.92 => 'white',
            $s < 0.15 => 'grey',
            $h >= 15 && $h < 45 && $l < 0.45 => 'brown',
            ($h < 15 || $h >= 345) && $l > 0.62 => 'pink',
            $h < 15, $h >= 345 => 'red',
            $h < 45 => 'orange',
            $h < 70 => 'yellow',
            $h < 165 => 'green',
            $h < 195 => 'cyan',
            $h < 250 => 'blue',
            $h < 290 => 'purple',
            default => 'pink',
        };
    }

    /** @return list<string> */
    public static function families(): array
    {
        return ['black', 'white', 'grey', 'brown', 'red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'purple', 'pink'];
    }

    /** Black or white text, whichever has the higher WCAG contrast ratio. */
    public static function contrastText(string $hex): string
    {
        [$r, $g, $b] = array_map(function (int $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        $l = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return ($l + 0.05) / 0.05 >= 1.05 / ($l + 0.05) ? '#000000' : '#FFFFFF';
    }
}

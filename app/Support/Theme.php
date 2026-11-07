<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Light or dark mode. Dark is the default; the toggle in the layout stores
 * "light" or "dark" in a plain (unencrypted) cookie that the browser sets itself.
 */
final class Theme
{
    public const COOKIE = 'theme';

    public const LIGHT = 'light';

    public const DARK = 'dark';

    public static function current(Request $request): string
    {
        return $request->cookie(self::COOKIE) === self::LIGHT ? self::LIGHT : self::DARK;
    }
}

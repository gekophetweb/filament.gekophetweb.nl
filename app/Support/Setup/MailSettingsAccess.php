<?php

namespace App\Support\Setup;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Who may open the mail settings page: a browser that redeemed the one-time link
 * from `php artisan setup:mail-url`, for config('setup.mail_token_minutes').
 */
class MailSettingsAccess
{
    private const SESSION_KEY = 'mail_settings.unlocked_until';

    /**
     * A new one-time token; any earlier one stops working.
     */
    public function issueToken(): string
    {
        $token = Str::random(48);
        $path = config('setup.mail_token_path');
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}.");
        }

        file_put_contents($path, json_encode([
            'token' => $token,
            'expires_at' => now()->addMinutes((int) config('setup.mail_token_minutes'))->getTimestamp(),
        ]));
        @chmod($path, 0600);

        return $token;
    }

    /**
     * Use up the token and unlock this browser's session. False for a wrong, used or expired token.
     */
    public function redeem(string $given): bool
    {
        $path = config('setup.mail_token_path');
        $stored = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($stored) || $given === '' || ! hash_equals((string) ($stored['token'] ?? ''), $given)) {
            return false;
        }

        @unlink($path);

        if ((int) ($stored['expires_at'] ?? 0) < now()->getTimestamp()) {
            return false;
        }

        Session::regenerate();
        Session::put(self::SESSION_KEY, now()->addMinutes((int) config('setup.mail_token_minutes'))->getTimestamp());

        return true;
    }

    public function isUnlocked(): bool
    {
        return (int) Session::get(self::SESSION_KEY, 0) >= now()->getTimestamp();
    }
}

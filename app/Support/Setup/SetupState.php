<?php

namespace App\Support\Setup;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Whether the first-run setup wizard is still open, its one-time token, and
 * who may reach it.
 */
class SetupState
{
    /** Headers a reverse proxy or the Cloudflare Tunnel adds to every request. */
    private const PROXY_HEADERS = ['X-Forwarded-For', 'X-Forwarded-Host', 'Forwarded', 'X-Real-IP', 'CF-Connecting-IP', 'CF-Ray'];

    /**
     * Setup is complete once the wizard has saved (lock file) or SSO was
     * already configured by hand, so existing installs never reopen it.
     */
    public function isComplete(): bool
    {
        return is_file(config('setup.lock_path')) || filled(config('services.sso.client_id'));
    }

    public function complete(): void
    {
        $this->ensureDirectory(config('setup.lock_path'));
        file_put_contents(config('setup.lock_path'), now()->toIso8601String()."\n");

        if (is_file(config('setup.token_path'))) {
            unlink(config('setup.token_path'));
        }
    }

    /**
     * The one-time token, created on first use and kept until setup completes.
     */
    public function token(): string
    {
        $path = config('setup.token_path');
        $token = is_file($path) ? trim((string) file_get_contents($path)) : '';

        if (strlen($token) < 32) {
            $token = Str::random(48);
            $this->ensureDirectory($path);
            file_put_contents($path, $token);
            @chmod($path, 0600);
        }

        return $token;
    }

    public function tokenMatches(string $given): bool
    {
        $path = config('setup.token_path');

        if ($given === '' || ! is_file($path)) {
            return false;
        }

        return hash_equals(trim((string) file_get_contents($path)), trim($given));
    }

    /**
     * A request straight to the published local port: from a private or
     * loopback address and without any proxy header. Requests through the
     * Cloudflare Tunnel always carry CF-Connecting-IP and X-Forwarded-For.
     */
    public function isDirectLocalRequest(Request $request): bool
    {
        foreach (self::PROXY_HEADERS as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        $remote = (string) $request->server('REMOTE_ADDR', '');

        return $remote !== '' && IpUtils::checkIp($remote, IpUtils::PRIVATE_SUBNETS);
    }

    private function ensureDirectory(string $file): void
    {
        $directory = dirname($file);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}.");
        }
    }
}

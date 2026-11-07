<?php

namespace App\Actions;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Connects the app by QR code. The QR holds the API URL and a one-time pairing code that
 * works for TTL_SECONDS; the app trades it at POST /api/v1/pair for its own token.
 * Only a hash of the code is stored, in the cache.
 */
class DevicePairing
{
    public const TTL_SECONDS = 600;

    public const TOKEN_LIFETIME_DAYS = 365;

    /**
     * @param  list<string>  $abilities
     * @return array{code: string, expires_at: Carbon, payload: string}
     */
    public function start(User $user, string $deviceName, array $abilities): array
    {
        $code = Str::random(40);
        $expiresAt = now()->addSeconds(self::TTL_SECONDS);

        Cache::put(self::key($code), [
            'user_id' => $user->id,
            'name' => $deviceName,
            'abilities' => $abilities,
        ], $expiresAt);

        $payload = json_encode([
            'type' => 'filament-spools-pairing',
            'api' => url('/api/v1'),
            'code' => $code,
            'expires_at' => $expiresAt->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES);

        return ['code' => $code, 'expires_at' => $expiresAt, 'payload' => $payload];
    }

    public function cancel(string $code): void
    {
        Cache::forget(self::key($code));
    }

    /**
     * Use up the code and create the device's token, or null when the code is unknown,
     * expired, already used, or the user has no room for another token.
     */
    public function claim(string $code, ?string $deviceName = null): ?NewAccessToken
    {
        $pairing = Cache::lock(self::key($code).':lock', 5)->get(fn () => Cache::pull(self::key($code)));
        $user = $pairing ? User::find($pairing['user_id']) : null;

        if (! $user || ! $user->hasRoomForToken()) {
            return null;
        }

        return $user->createToken(
            $deviceName ?: $pairing['name'],
            $pairing['abilities'],
            now()->addDays(self::TOKEN_LIFETIME_DAYS),
        );
    }

    /** The QR code as inline SVG. */
    public static function qrSvg(string $payload): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(260, 2), new SvgImageBackEnd)))->writeString($payload);

        return preg_replace('/^<\?xml.*?\?>\s*/', '', $svg);
    }

    private static function key(string $code): string
    {
        return 'device-pairing:'.hash('sha256', $code);
    }
}

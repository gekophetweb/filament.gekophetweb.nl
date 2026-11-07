<?php

namespace App\Http\Controllers\Api;

use App\Actions\DevicePairing;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trades the one-time code from the Devices page QR for a device token.
 * A bad code answers 401, which counts toward the IP's failed-authentication limit.
 */
class PairController extends Controller
{
    public function __invoke(Request $request, DevicePairing $pairing): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $token = $pairing->claim($data['code'], $data['device_name'] ?? null);

        if (! $token) {
            return response()->json(['message' => 'This pairing code is invalid, already used or expired. Show a new QR code.'], 401);
        }

        $user = $token->accessToken->tokenable;

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $token->accessToken->abilities,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'api' => url('/api/v1'),
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]], 201);
    }
}

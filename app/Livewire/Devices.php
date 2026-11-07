<?php

namespace App\Livewire;

use App\Actions\DevicePairing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Personal access tokens for the Android app and MCP clients.
 */
#[Layout('layouts.app')]
#[Title('Devices')]
class Devices extends Component
{
    public const ABILITIES = [
        'read' => ['spools:read'],
        'write' => ['spools:read', 'spools:write'],
    ];

    #[Validate('required|string|max:60')]
    public string $name = '';

    #[Validate('required|in:read,write')]
    public string $access = 'read';

    /** Shown exactly once after creation. */
    public ?string $plainToken = null;

    /** One-time pairing code behind the QR code currently shown. */
    #[Locked]
    public ?string $pairingCode = null;

    #[Locked]
    public ?string $pairingPayload = null;

    #[Locked]
    public ?string $pairingExpiresAt = null;

    public function create(): void
    {
        if (! $this->validateRoomForToken()) {
            return;
        }

        $this->plainToken = Auth::user()
            ->createToken($this->name, self::ABILITIES[$this->access], now()->addYear())
            ->plainTextToken;

        $this->reset('name', 'access');
    }

    /**
     * Show a QR code with the API URL and a pairing code that works for 10 minutes.
     */
    public function showQr(DevicePairing $pairing): void
    {
        if (! $this->validateRoomForToken()) {
            return;
        }

        $this->cancelQr($pairing);
        $started = $pairing->start(Auth::user(), $this->name, self::ABILITIES[$this->access]);

        $this->pairingCode = $started['code'];
        $this->pairingPayload = $started['payload'];
        $this->pairingExpiresAt = $started['expires_at']->toIso8601String();
        $this->reset('name', 'access', 'plainToken');
    }

    public function cancelQr(DevicePairing $pairing): void
    {
        if ($this->pairingCode) {
            $pairing->cancel($this->pairingCode);
        }

        $this->reset('pairingCode', 'pairingPayload', 'pairingExpiresAt');
    }

    private function validateRoomForToken(): bool
    {
        $this->validate();

        if (! Auth::user()->hasRoomForToken()) {
            $max = (int) config('access.max_tokens_per_user');
            $this->addError('name', "You can have at most {$max} active tokens. Revoke one you no longer use first.");

            return false;
        }

        return true;
    }

    public function revoke(int $id): void
    {
        Auth::user()->tokens()->whereKey($id)->delete();
    }

    public function render(): View
    {
        $pairingActive = $this->pairingPayload && now()->lt($this->pairingExpiresAt);

        return view('livewire.devices', [
            'tokens' => Auth::user()->tokens()->latest()->get(),
            'qrSvg' => $pairingActive ? DevicePairing::qrSvg($this->pairingPayload) : null,
        ]);
    }
}

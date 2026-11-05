<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
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

    public function create(): void
    {
        $this->validate();

        $this->plainToken = Auth::user()
            ->createToken($this->name, self::ABILITIES[$this->access], now()->addYear())
            ->plainTextToken;

        $this->reset('name', 'access');
    }

    public function revoke(int $id): void
    {
        Auth::user()->tokens()->whereKey($id)->delete();
    }

    public function render(): View
    {
        return view('livewire.devices', [
            'tokens' => Auth::user()->tokens()->latest()->get(),
        ]);
    }
}

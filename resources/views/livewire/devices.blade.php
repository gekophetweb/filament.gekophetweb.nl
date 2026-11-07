<div class="max-w-3xl space-y-6">
    <h1 class="text-xl font-semibold">Devices and API tokens</h1>
    <p class="text-sm text-stone-500">
        Create a token for the Android app or an MCP client. Read-only tokens cannot change anything.
        API base URL: <code class="font-mono">{{ url('/api/v1') }}</code> · MCP endpoint: <code class="font-mono">{{ url('/mcp') }}</code>
    </p>

    @if ($plainToken)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">
            <p class="text-sm font-medium">Copy this token now. It will not be shown again.</p>
            <code class="mt-2 block break-all font-mono text-sm">{{ $plainToken }}</code>
        </div>
    @endif

    <form wire:submit="create" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm">
        <label class="flex-1">
            <span class="text-xs font-medium text-stone-500">Device name</span>
            <input wire:model="name" maxlength="60" placeholder="Pixel 9" class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
            @error('name') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
        </label>
        <label>
            <span class="text-xs font-medium text-stone-500">Access</span>
            <select wire:model="access" class="mt-1 block rounded-md border-stone-300 px-2 py-1.5">
                <option value="read">Read only</option>
                <option value="write">Read and write</option>
            </select>
        </label>
        <button class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white">Create token</button>
        <button type="button" wire:click="showQr" class="rounded-md border border-stone-300 px-3 py-2 text-sm hover:bg-stone-50"
                title="Scan with the app: it gets the API address and its own token">Show QR code</button>
    </form>

    @if ($qrSvg)
        <div wire:key="qr-{{ $pairingExpiresAt }}" class="flex flex-wrap items-center gap-6 rounded-xl bg-white p-4 shadow-sm"
             x-data="{ left: Math.max(0, Math.round((new Date(@js($pairingExpiresAt)) - Date.now()) / 1000)) }"
             x-init="const timer = setInterval(() => { left = Math.max(0, left - 1); if (left === 0) { clearInterval(timer); $wire.cancelQr(); } }, 1000)">
            <div class="rounded-lg bg-white p-2 ring-1 ring-stone-200" role="img" aria-label="QR code to connect the app">{!! $qrSvg !!}</div>
            <div class="space-y-2 text-sm">
                <p class="font-medium">Scan this with the app</p>
                <p class="text-stone-600">It holds the API address <code class="font-mono">{{ url('/api/v1') }}</code> and a one-time code.
                    The app trades the code for its own token, so the QR works only once.</p>
                <p class="text-stone-600">Expires in <span class="font-semibold tabular-nums" x-text="`${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`"></span>.</p>
                <button type="button" wire:click="cancelQr" class="text-sm text-red-700 hover:underline">Hide and cancel</button>
            </div>
        </div>
    @endif

    <table class="w-full rounded-xl bg-white text-sm shadow-sm">
        <thead class="text-left text-xs text-stone-500">
            <tr><th class="p-3">Name</th><th>Abilities</th><th>Last used</th><th>Expires</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($tokens as $token)
            <tr wire:key="token-{{ $token->id }}" class="border-t border-stone-100">
                <td class="p-3">{{ $token->name }}</td>
                <td class="font-mono text-xs">{{ implode(', ', $token->abilities) }}</td>
                <td>{{ $token->last_used_at?->diffForHumans() ?? 'never' }}</td>
                <td>{{ $token->expires_at?->toDateString() ?? 'never' }}</td>
                <td class="pr-3 text-right">
                    <button wire:click="revoke({{ $token->id }})" wire:confirm="Revoke this token?" class="text-red-700">Revoke</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="p-3 text-stone-500">No tokens yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

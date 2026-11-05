<div class="grid gap-6 lg:grid-cols-3">
    <section class="rounded-xl bg-white shadow-sm">
        <x-swatch :product="$spool->product" size="h-40" />
        <div class="space-y-3 p-4">
            <h1 class="text-xl font-semibold">{{ $spool->product->article_name }}</h1>
            <p class="text-sm text-stone-500">
                {{ $spool->product->brand->name }} · {{ $spool->product->material->name }} {{ $spool->product->line }}
                · <span class="font-mono">{{ $spool->product->article_number }}</span>
            </p>
            <p class="text-sm">{{ $spool->status->label() }} · {{ $spool->remaining_weight }} / {{ $spool->initial_weight }} g
                @if ($spool->is_refill) · refill @endif</p>
            <p class="font-mono text-xs text-stone-400" title="Encode this in a QR/NFC label">{{ $spool->uuid }}</p>

            <form wire:submit="save" class="space-y-3 border-t border-stone-100 pt-3">
                <label class="block">
                    <span class="text-xs font-medium text-stone-500">Location / AMS slot</span>
                    <input wire:model="location" maxlength="100" class="mt-1 w-full rounded-md border-stone-300 px-2 py-1">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-stone-500">Notes</span>
                    <textarea wire:model="notes" maxlength="2000" rows="3" class="mt-1 w-full rounded-md border-stone-300 px-2 py-1"></textarea>
                </label>
                <button class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white">Save</button>
                @if (session('saved')) <span class="text-sm text-emerald-700">{{ session('saved') }}</span> @endif
            </form>
        </div>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm lg:col-span-2">
        <h2 class="mb-3 font-semibold">Weight history</h2>
        @if ($chart)
            <svg viewBox="0 0 600 160" class="h-48 w-full" role="img" aria-label="Remaining weight over time" preserveAspectRatio="none">
                <line x1="0" y1="160" x2="600" y2="160" stroke="#d6d3d1" />
                <polyline points="{{ $chart }}" fill="none" stroke="#047857" stroke-width="2" vector-effect="non-scaling-stroke" />
            </svg>
        @else
            <p class="text-sm text-stone-500">Weigh this spool at least twice to see a chart.</p>
        @endif

        <table class="mt-4 w-full text-sm">
            <thead class="text-left text-xs text-stone-500"><tr><th class="py-1">When</th><th>Event</th><th>Weight</th><th>Note</th></tr></thead>
            <tbody>
            @foreach ($events as $event)
                <tr class="border-t border-stone-100">
                    <td class="py-1">{{ $event->created_at->format('Y-m-d H:i') }}</td>
                    <td class="capitalize">{{ $event->type->value }}</td>
                    <td>{{ $event->weight !== null ? $event->weight.' g' : '' }}</td>
                    <td class="text-stone-500">{{ $event->note }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>

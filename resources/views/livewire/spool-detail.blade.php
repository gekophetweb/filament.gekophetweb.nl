<div class="grid gap-6 lg:grid-cols-3">
    <section class="rounded-xl bg-white shadow-sm">
        <x-swatch :product="$spool->product" size="h-32" />
        <div class="space-y-3 p-4">
            <h1 class="text-xl font-semibold">
                <a href="{{ route('filaments.show', $spool->product) }}" class="hover:underline">{{ $spool->product->article_name }}</a>
            </h1>
            <p class="text-sm text-stone-500">
                {{ $spool->product->brand->name }} · {{ $spool->product->material->name }} {{ $spool->product->line }}
                · <span class="font-mono">{{ $spool->product->article_number }}</span>
            </p>
            <p class="text-sm">{{ $spool->status->label() }} @if ($spool->is_refill) · refill @endif</p>
            <p class="font-mono text-xs text-stone-400" title="Encode this in a QR/NFC label">{{ $spool->uuid }}</p>
        </div>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm lg:col-span-2">
        <h2 class="mb-3 font-semibold">History</h2>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-stone-500"><tr><th class="py-1 font-medium">When</th><th class="font-medium">What</th><th class="font-medium">Note</th></tr></thead>
            <tbody>
            @foreach ($events as $event)
                <tr wire:key="event-{{ $event->id }}" class="border-t border-stone-100">
                    <td class="whitespace-nowrap py-1.5 pr-4">{{ $event->created_at->format('Y-m-d H:i') }}</td>
                    <td class="pr-4">{{ $event->type->label() }}</td>
                    <td class="text-stone-500">{{ $event->note }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>

<div class="space-y-6">
    <a href="{{ route('catalog') }}" class="text-sm text-stone-500 hover:text-stone-900">← Catalog</a>

    <section class="flex flex-wrap items-center gap-5 rounded-xl bg-white p-5 shadow-sm">
        <span class="h-16 w-16 shrink-0 rounded-full ring-1 ring-stone-300" style="background: {{ $filament->swatchCss() }}"></span>
        <div class="min-w-48 flex-1 space-y-1">
            <h1 class="text-xl font-semibold">{{ $filament->article_name }}</h1>
            <p class="text-sm text-stone-500">
                {{ $filament->brand->name }} · {{ $filament->material->name }} {{ $filament->line }}
                · <span class="font-mono">{{ $filament->article_number }}</span>
                · <span class="font-mono">{{ $filament->color_hex }}@if ($filament->color_hex_2) / {{ $filament->color_hex_2 }}@endif</span>
            </p>
        </div>
        @if ($url = $filament->buyUrl())
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
               class="rounded-md bg-stone-900 px-3 py-2 text-sm text-white hover:bg-stone-700">Buy at Bambu Lab ↗</a>
        @endif
        <x-inventory-controls :product="$filament" class="w-full border-t border-stone-100 pt-4" />
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-semibold">Usage, last 12 months</h2>
        <x-usage-chart :months="$months" label="{{ $filament->article_name }}: spools added and used up per month" />
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-semibold">History</h2>
        @if ($history->isEmpty())
            <p class="text-sm text-stone-500">No spools of this filament yet. Use + to add a boxed spool.</p>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-stone-500"><tr><th class="py-1 font-medium">When</th><th class="font-medium">What</th><th class="font-medium">Spool</th><th class="font-medium">Note</th></tr></thead>
                <tbody>
                @foreach ($history as $event)
                    <tr wire:key="event-{{ $event->id }}" class="border-t border-stone-100">
                        <td class="whitespace-nowrap py-1.5 pr-4">{{ $event->created_at->format('Y-m-d H:i') }}</td>
                        <td class="pr-4">{{ $event->type->label() }}</td>
                        <td class="pr-4 font-mono text-xs text-stone-500">
                            @if ($event->spool && ! $event->spool->trashed())
                                <a href="{{ route('spools.show', $event->spool) }}" class="hover:underline">{{ Str::limit($event->spool->uuid, 8, '') }}</a>
                            @else
                                {{ Str::limit((string) $event->spool?->uuid, 8, '') }}
                            @endif
                        </td>
                        <td class="text-stone-500">{{ $event->note }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>

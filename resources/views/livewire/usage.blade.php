<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-xl font-semibold">Usage</h1>
        <div class="ml-auto flex rounded-lg border border-stone-300 bg-white p-0.5 text-sm" role="group" aria-label="Period">
            @foreach ($periods as $period)
                <button wire:key="period-{{ $period }}" wire:click="$set('months', {{ $period }})"
                        @class(['rounded-md px-3 py-1', 'bg-stone-900 text-white' => $months === $period, 'text-stone-600 hover:text-stone-900' => $months !== $period])>
                    {{ $period }} months
                </button>
            @endforeach
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ([
            'used_up' => "Used up, last {$months} months",
            'added' => "Added, last {$months} months",
            'boxed' => 'Boxed now',
            'on_spool' => 'On spool now',
        ] as $key => $label)
            <div wire:key="total-{{ $key }}" class="rounded-xl bg-white p-4 shadow-sm">
                <dt class="text-xs text-stone-500">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $totals[$key] }}</dd>
            </div>
        @endforeach
    </dl>

    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-semibold">Spools per month</h2>
        <x-usage-chart :months="$monthly" />
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-semibold">Most used filaments</h2>
        @if ($topUsed->isEmpty())
            <p class="text-sm text-stone-500">Nothing used up yet. Mark a spool as used up in the catalog to see it here.</p>
        @else
            <ol class="divide-y divide-stone-100">
                @foreach ($topUsed as $product)
                    <li wire:key="top-{{ $product->id }}" class="flex items-center gap-3 py-2">
                        <span class="h-6 w-6 shrink-0 rounded-full ring-1 ring-stone-300" style="background: {{ $product->swatchCss() }}"></span>
                        <a href="{{ route('filaments.show', $product) }}" class="flex-1 hover:underline">
                            {{ $product->article_name }}
                            <span class="text-xs text-stone-500">{{ $product->material->name }} {{ $product->line }} · {{ $product->article_number }}</span>
                        </a>
                        <span class="text-sm font-semibold">{{ $product->used_up_count }}</span>
                        <span class="text-xs text-stone-500">used up</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>

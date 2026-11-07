<div>
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <label class="flex-1 min-w-56">
            <span class="text-xs font-medium text-stone-500">Search name or article number</span>
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="e.g. Jade White or 10100"
                   class="mt-1 w-full rounded-lg border-stone-300 bg-white px-3 py-2 shadow-sm">
        </label>

        @foreach (['material' => $materials, 'line' => $lines] as $field => $options)
            <label>
                <span class="text-xs font-medium capitalize text-stone-500">{{ $field }}</span>
                <select wire:model.live="{{ $field }}" class="mt-1 block rounded-lg border-stone-300 bg-white px-3 py-2 shadow-sm">
                    <option value="">All</option>
                    @foreach ($options as $option)
                        <option wire:key="{{ $field }}-{{ $option }}" value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach

        <label>
            <span class="text-xs font-medium text-stone-500">Owned</span>
            <select wire:model.live="owned" class="mt-1 block rounded-lg border-stone-300 bg-white px-3 py-2 shadow-sm">
                <option value="">All</option>
                <option value="1">Owned</option>
                <option value="boxed">Boxed</option>
                <option value="on_spool">On spool</option>
                <option value="running_out">Running out (none boxed)</option>
                <option value="0">Not owned</option>
            </select>
        </label>

        <button wire:click="resetFilters" class="rounded-lg px-3 py-2 text-sm text-stone-500 hover:text-stone-900">Reset</button>

        <div class="ml-auto inline-flex rounded-lg border border-stone-300 bg-white p-0.5 shadow-sm" role="group" aria-label="View">
            @foreach (['list' => 'List', 'blocks' => 'Blocks'] as $option => $name)
                <button type="button" wire:key="display-{{ $option }}" wire:click="$set('display', '{{ $option }}')"
                        aria-pressed="{{ $display === $option ? 'true' : 'false' }}"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm',
                            'bg-stone-900 text-white' => $display === $option,
                            'text-stone-600 hover:text-stone-900' => $display !== $option,
                        ])>{{ $name }}</button>
            @endforeach
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Color family">
        @foreach ($families as $family)
            <button wire:key="family-{{ $family }}" wire:click="$set('color', '{{ $color === $family ? '' : $family }}')"
                    @class([
                        'rounded-full border px-3 py-1 text-xs capitalize',
                        'border-emerald-700 bg-emerald-700 text-white' => $color === $family,
                        'border-stone-300 bg-white' => $color !== $family,
                    ])>{{ $family }}</button>
        @endforeach
    </div>

    @if ($display === 'blocks')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($products as $product)
            <article wire:key="product-{{ $product->id }}" @class(['flex flex-col rounded-xl bg-white shadow-sm', 'ring-2 ring-emerald-600/40' => $product->owned_count > 0])>
                <a href="{{ route('filaments.show', $product) }}" class="block" tabindex="-1" aria-hidden="true">
                    <x-swatch :product="$product" />
                </a>
                <div class="flex flex-1 flex-col gap-2 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold leading-tight">
                            <a href="{{ route('filaments.show', $product) }}" class="hover:underline">{{ $product->article_name }}</a>
                        </h2>
                        <span class="font-mono text-xs text-stone-500">{{ $product->article_number }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1 text-xs">
                        <span class="rounded bg-stone-800 px-1.5 py-0.5 text-white">{{ $product->material->name }}</span>
                        @if ($product->line)
                            <span class="rounded bg-stone-200 px-1.5 py-0.5">{{ $product->line }}</span>
                        @endif
                    </div>
                    <div class="mt-auto space-y-3 pt-2">
                        <x-inventory-controls :product="$product" />
                        @if ($url = $product->buyUrl())
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                               class="inline-block rounded-md bg-stone-900 px-2 py-1 text-xs text-white hover:bg-stone-700">Buy ↗</a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full py-12 text-center text-stone-500">No filaments match these filters.</p>
        @endforelse
    </div>
    @else
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-stone-200 text-left text-xs text-stone-500">
                <tr>
                    <th class="px-3 py-2 font-medium">Article no.</th>
                    <th class="px-3 py-2 font-medium">Filament</th>
                    <th class="px-3 py-2 font-medium">Type</th>
                    <th class="px-3 py-2 font-medium">Color</th>
                    <th class="px-3 py-2 font-medium">In stock</th>
                    <th class="px-3 py-2 font-medium"><span class="sr-only">Buy</span></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                <tr wire:key="product-{{ $product->id }}" @class(['border-t border-stone-100 align-middle', 'bg-emerald-50/40' => $product->owned_count > 0])>
                    <td class="px-3 py-2 font-mono text-xs text-stone-600">{{ $product->article_number }}</td>
                    <td class="px-3 py-2">
                        <a href="{{ route('filaments.show', $product) }}" class="flex items-center gap-3 font-medium hover:underline">
                            <span class="h-7 w-7 shrink-0 rounded-full ring-1 ring-stone-300" style="background: {{ $product->swatchCss() }}"></span>
                            {{ $product->article_name }}
                        </a>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                        <span class="rounded bg-stone-800 px-1.5 py-0.5 text-xs text-white">{{ $product->material->name }}</span>
                        @if ($product->line)
                            <span class="rounded bg-stone-200 px-1.5 py-0.5 text-xs">{{ $product->line }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-stone-500">
                        {{ $product->color_hex }}@if ($product->color_hex_2) / {{ $product->color_hex_2 }}@endif
                    </td>
                    <td class="px-3 py-2"><x-inventory-controls :product="$product" :wrap="false" /></td>
                    <td class="px-3 py-2 text-right">
                        @if ($url = $product->buyUrl())
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                               class="whitespace-nowrap rounded-md bg-stone-900 px-2 py-1 text-xs text-white hover:bg-stone-700">Buy ↗</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-stone-500">No filaments match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endif

    <div class="mt-6">{{ $products->links() }}</div>
</div>

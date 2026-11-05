<div>
    <div class="mb-6 flex flex-wrap items-end gap-3">
        <label class="flex-1 min-w-56">
            <span class="text-xs font-medium text-stone-500">Search name or SKU</span>
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="e.g. Jade White or 10100"
                   class="mt-1 w-full rounded-lg border-stone-300 bg-white px-3 py-2 shadow-sm">
        </label>

        @foreach (['material' => $materials, 'line' => $lines] as $field => $options)
            <label>
                <span class="text-xs font-medium capitalize text-stone-500">{{ $field }}</span>
                <select wire:model.live="{{ $field }}" class="mt-1 block rounded-lg border-stone-300 bg-white px-3 py-2 shadow-sm">
                    <option value="">All</option>
                    @foreach ($options as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach

        <label>
            <span class="text-xs font-medium text-stone-500">Owned</span>
            <select wire:model.live="owned" class="mt-1 block rounded-lg border-stone-300 bg-white px-3 py-2 shadow-sm">
                <option value="">All</option>
                <option value="1">Owned</option>
                <option value="0">Not owned</option>
                <option value="low">Low stock</option>
            </select>
        </label>

        <button wire:click="resetFilters" class="rounded-lg px-3 py-2 text-sm text-stone-500 hover:text-stone-900">Reset</button>
    </div>

    <div class="mb-6 flex flex-wrap gap-2" role="group" aria-label="Color family">
        @foreach ($families as $family)
            <button wire:click="$set('color', '{{ $color === $family ? '' : $family }}')"
                    @class([
                        'rounded-full border px-3 py-1 text-xs capitalize',
                        'border-emerald-700 bg-emerald-700 text-white' => $color === $family,
                        'border-stone-300 bg-white' => $color !== $family,
                    ])>{{ $family }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
        @forelse ($products as $product)
            <article wire:key="product-{{ $product->id }}" class="flex flex-col rounded-xl bg-white shadow-sm">
                <x-swatch :product="$product" />
                <div class="flex flex-1 flex-col gap-2 p-3">
                    <h2 class="font-semibold leading-tight">{{ $product->article_name }}</h2>
                    <div class="flex flex-wrap gap-1 text-xs">
                        <span class="rounded bg-stone-800 px-1.5 py-0.5 text-white">{{ $product->material->name }}</span>
                        @if ($product->line)
                            <span class="rounded bg-stone-200 px-1.5 py-0.5">{{ $product->line }}</span>
                        @endif
                    </div>
                    <p class="font-mono text-xs text-stone-500">{{ $product->article_number }}</p>
                    <div class="mt-auto flex items-center justify-between pt-2">
                        <span @class(['text-xs', 'font-semibold text-emerald-700' => $product->owned_count > 0, 'text-stone-400' => $product->owned_count === 0])>
                            owned: {{ $product->owned_count }}
                        </span>
                        <button wire:click="addSpool({{ $product->id }})" wire:loading.attr="disabled"
                                class="rounded-md bg-stone-900 px-2 py-1 text-xs text-white hover:bg-stone-700">+ Spool</button>
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full py-12 text-center text-stone-500">No filaments match these filters.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $products->links() }}</div>
</div>

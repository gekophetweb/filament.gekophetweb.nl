<div>
    <div class="mb-6 flex items-center gap-3">
        <h1 class="text-xl font-semibold">My spools</h1>
        <select wire:model.live="status" class="ml-auto rounded-lg border-stone-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="active">Active</option>
            <option value="low">Low stock</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
            <option value="all">All</option>
        </select>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($spools as $spool)
            <article wire:key="spool-{{ $spool->uuid }}" class="flex flex-col rounded-xl bg-white shadow-sm">
                <a href="{{ route('spools.show', $spool) }}"><x-swatch :product="$spool->product" size="h-16" /></a>
                <div class="flex flex-1 flex-col gap-2 p-3">
                    <a href="{{ route('spools.show', $spool) }}" class="font-semibold leading-tight hover:underline">{{ $spool->product->article_name }}</a>
                    <div class="flex flex-wrap gap-1 text-xs">
                        <span class="rounded bg-stone-800 px-1.5 py-0.5 text-white">{{ $spool->product->material->name }}</span>
                        <span class="rounded bg-stone-200 px-1.5 py-0.5">{{ $spool->status->label() }}</span>
                        @if ($spool->location)
                            <span class="rounded bg-sky-100 px-1.5 py-0.5 text-sky-800">{{ $spool->location }}</span>
                        @endif
                    </div>

                    <div>
                        <div class="h-2 overflow-hidden rounded bg-stone-200" role="progressbar"
                             aria-valuenow="{{ $spool->remainingPercent() }}" aria-valuemin="0" aria-valuemax="100">
                            <div @class(['h-full', 'bg-red-500' => $spool->isLow(), 'bg-emerald-600' => ! $spool->isLow()])
                                 style="width: {{ $spool->remainingPercent() }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-stone-500">{{ $spool->remaining_weight }} / {{ $spool->initial_weight }} g</p>
                    </div>

                    @if ($spool->status !== \App\Enums\SpoolStatus::Empty)
                        <form wire:submit="weigh('{{ $spool->uuid }}')" class="mt-auto flex gap-2 pt-2">
                            <input type="number" min="0" max="20000" wire:model="weights.{{ $spool->uuid }}" placeholder="grams left"
                                   class="w-full rounded-md border-stone-300 px-2 py-1 text-sm">
                            <button class="rounded-md bg-stone-900 px-2 py-1 text-xs text-white">Save</button>
                            <button type="button" wire:click="markEmpty('{{ $spool->uuid }}')" wire:confirm="Mark this spool as empty?"
                                    class="rounded-md px-2 py-1 text-xs text-red-700 hover:bg-red-50">Empty</button>
                        </form>
                        @error("weights.{$spool->uuid}") <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                    @endif
                </div>
            </article>
        @empty
            <p class="col-span-full py-12 text-center text-stone-500">
                No spools here yet. Add one from the <a href="{{ route('catalog') }}" class="underline">catalog</a>.
            </p>
        @endforelse
    </div>

    <div class="mt-6">{{ $spools->links() }}</div>
</div>

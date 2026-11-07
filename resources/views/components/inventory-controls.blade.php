{{-- Boxed / on spool counts with their buttons. The parent Livewire component provides
     addBoxed, removeBoxed, putOnSpool and useUp, each taking the product id. --}}
@props(['product', 'wrap' => true])
@php $button = 'inline-flex h-7 min-w-7 items-center justify-center whitespace-nowrap rounded-md border border-stone-300 bg-white px-1.5 text-xs hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-40'; @endphp

<div {{ $attributes->class(['flex items-center gap-x-4 gap-y-2', 'flex-wrap' => $wrap]) }}>
    <div class="flex items-center gap-1.5" role="group" aria-label="Boxed spools of {{ $product->article_name }}">
        <button type="button" wire:click="removeBoxed({{ $product->id }})" wire:loading.attr="disabled"
                @disabled($product->boxed_count === 0) class="{{ $button }}" title="Remove a boxed spool (added by mistake)">−</button>
        <span class="min-w-16 text-center text-sm">
            <span @class(['font-semibold', 'text-stone-400' => $product->boxed_count === 0])>{{ $product->boxed_count }}</span>
            <span class="text-xs text-stone-500">boxed</span>
        </span>
        <button type="button" wire:click="addBoxed({{ $product->id }})" wire:loading.attr="disabled"
                class="{{ $button }}" title="Add a boxed spool">+</button>
    </div>

    <button type="button" wire:click="putOnSpool({{ $product->id }})" wire:loading.attr="disabled"
            @disabled($product->boxed_count === 0) class="{{ $button }}" title="Take a boxed spool out and put it on the spool holder">
        Put on spool →
    </button>

    <div class="flex items-center gap-1.5">
        <span class="min-w-16 text-center text-sm">
            <span @class(['font-semibold', 'text-stone-400' => $product->on_spool_count === 0])>{{ $product->on_spool_count }}</span>
            <span class="text-xs text-stone-500">on spool</span>
        </span>
        <button type="button" wire:click="useUp({{ $product->id }})" wire:loading.attr="disabled"
                wire:confirm="Mark one spool of {{ $product->article_name }} as used up?"
                @disabled($product->on_spool_count === 0) class="{{ $button }}" title="Mark a spool on the holder as used up">
            Used up
        </button>
    </div>
</div>

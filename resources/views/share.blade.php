@extends('layouts.app', ['title' => "{$owner->name}'s filament"])

@section('content')
    <div class="space-y-4">
        <div>
            <h1 class="text-xl font-semibold">{{ $owner->name }}'s filament</h1>
            <p class="text-sm text-stone-500">Read-only list of the spools they have right now.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:max-w-md">
            @foreach (['In boxes' => $boxedTotal, 'On spools' => $onSpoolTotal] as $label => $count)
                <div class="rounded-xl bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs text-stone-500">{{ $label }}</p>
                    <p class="text-2xl font-semibold tabular-nums">{{ $count }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-stone-200 text-left text-xs text-stone-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Article no.</th>
                        <th class="px-3 py-2 font-medium">Filament</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 text-right font-medium">In boxes</th>
                        <th class="px-3 py-2 text-right font-medium">On spools</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    <tr class="border-t border-stone-100">
                        <td class="px-3 py-2 font-mono text-xs text-stone-600">{{ $product->article_number }}</td>
                        <td class="px-3 py-2">
                            <span class="flex items-center gap-3 font-medium">
                                <span class="h-6 w-6 shrink-0 rounded-full ring-1 ring-stone-300" style="background: {{ $product->swatchCss() }}"></span>
                                {{ $product->article_name }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">
                            <span class="rounded bg-stone-800 px-1.5 py-0.5 text-xs text-white">{{ $product->material->name }}</span>
                            @if ($product->line)
                                <span class="rounded bg-stone-200 px-1.5 py-0.5 text-xs">{{ $product->line }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $product->boxed_count }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $product->on_spool_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-12 text-center text-stone-500">No spools right now.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

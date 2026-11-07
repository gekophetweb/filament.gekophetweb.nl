<div>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-xl font-semibold">My spools</h1>
        <select wire:model.live="material" aria-label="Filament type" class="ml-auto rounded-lg border-stone-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">All filament types</option>
            @foreach ($materials as $name)
                <option wire:key="material-{{ $name }}" value="{{ $name }}">{{ $name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" aria-label="Status" class="rounded-lg border-stone-300 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="active">Boxed and on spool</option>
            @foreach ($statuses as $s)
                <option wire:key="status-{{ $s->value }}" value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
        <a href="{{ route('spools.export') }}" class="whitespace-nowrap rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm shadow-sm hover:bg-stone-50"
           title="Download all your spools (any status) as a CSV file">Export CSV ↓</a>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:max-w-md">
        @foreach (['In boxes' => $boxedCount, 'On spools' => $onSpoolCount] as $label => $count)
            <div wire:key="count-{{ $label }}" class="rounded-xl bg-white px-4 py-3 shadow-sm">
                <p class="text-xs text-stone-500">{{ $label }}{{ $material !== '' ? " · {$material}" : '' }}</p>
                <p class="text-2xl font-semibold tabular-nums">{{ $count }}</p>
            </div>
        @endforeach
    </div>

    @if ($importResult)
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm" role="status">
            @php
                $summary = "{$importResult['created']} added, {$importResult['updated']} updated, {$importResult['unchanged']} unchanged"
                    .($importResult['errors'] ? ', '.count($importResult['errors']).' skipped' : '');
            @endphp
            <p class="font-medium">Import done: {{ $summary }}.</p>
            @if ($importResult['errors'])
                <ul class="mt-2 list-disc space-y-0.5 pl-5 text-stone-700">
                    @foreach (array_slice($importResult['errors'], 0, 20) as $i => $error)
                        <li wire:key="import-error-{{ $i }}">{{ $error }}</li>
                    @endforeach
                    @if (count($importResult['errors']) > 20)
                        <li>… and {{ count($importResult['errors']) - 20 }} more.</li>
                    @endif
                </ul>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-stone-200 text-left text-xs text-stone-500">
                <tr>
                    <th class="px-3 py-2 font-medium">Filament</th>
                    <th class="px-3 py-2 font-medium">Article no.</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($spools as $spool)
                <tr wire:key="spool-{{ $spool->uuid }}" class="border-t border-stone-100">
                    <td class="px-3 py-2">
                        <a href="{{ route('spools.show', $spool) }}" class="flex items-center gap-3 font-medium hover:underline">
                            <span class="h-6 w-6 shrink-0 rounded-full ring-1 ring-stone-300" style="background: {{ $spool->product->swatchCss() }}"></span>
                            {{ $spool->product->article_name }}
                            <span class="text-xs font-normal text-stone-500">{{ $spool->product->material->name }} {{ $spool->product->line }}</span>
                        </a>
                    </td>
                    <td class="px-3 py-2 font-mono text-xs text-stone-600">{{ $spool->product->article_number }}</td>
                    <td class="px-3 py-2"><span class="rounded bg-stone-200 px-1.5 py-0.5 text-xs">{{ $spool->status->label() }}</span></td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        @if ($spool->status === \App\Enums\SpoolStatus::Boxed)
                            <button wire:click="putOnSpool('{{ $spool->uuid }}')" class="rounded-md border border-stone-300 px-2 py-1 text-xs hover:bg-stone-50">Put on spool</button>
                        @endif
                        @if (in_array($spool->status, \App\Enums\SpoolStatus::owned(), true))
                            <button wire:click="useUp('{{ $spool->uuid }}')" wire:confirm="Mark this spool as used up?"
                                    class="rounded-md border border-stone-300 px-2 py-1 text-xs hover:bg-stone-50">Used up</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-12 text-center text-stone-500">
                    No spools here yet. Add one from the <a href="{{ route('catalog') }}" class="underline">catalog</a>.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $spools->links() }}</div>

    <section class="mt-8 rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Import spools from CSV</h2>
        <p class="mt-1 text-sm text-stone-600">
            Use a file from <strong>Export CSV</strong>, edited in a spreadsheet if you like. Only <code>article_number</code> is required.
            Rows with the <code>id</code> of one of your spools update it; other rows are added as new spools. Nothing is deleted.
        </p>
        <form wire:submit="import" class="mt-3 flex flex-wrap items-center gap-3">
            <input type="file" wire:model="importFile" accept=".csv,text/csv"
                   class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-stone-200 file:px-3 file:py-1.5 file:text-sm hover:file:bg-stone-300">
            <button type="submit" wire:loading.attr="disabled" wire:target="importFile,import"
                    class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700 disabled:opacity-50">Import</button>
            <span wire:loading wire:target="importFile,import" class="text-sm text-stone-500">Working…</span>
        </form>
        @error('importFile') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
    </section>
</div>

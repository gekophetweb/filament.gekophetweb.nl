<div class="max-w-3xl space-y-4">
    <h1 class="text-xl font-semibold">Share &amp; export</h1>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Share a read-only link</h2>
        <p class="mt-1 text-sm text-stone-600">
            Anyone with the link sees which filaments you have and how many are boxed or on spool, without signing in.
            No purchase dates or history.
        </p>
        @if ($shareUrl)
            <div class="mt-3 flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                <input type="text" readonly value="{{ $shareUrl }}" aria-label="Share link" x-ref="link" @focus="$el.select()"
                       class="min-w-0 flex-1 rounded-md border-stone-300 bg-stone-50 px-2 py-1.5 font-mono text-xs">
                <button type="button" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700"
                        @click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)"
                        x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                <a href="{{ $shareUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50">Open ↗</a>
                <button type="button" wire:click="newShareLink" wire:confirm="Make a new link? The current link stops working."
                        class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50">New link</button>
                <button type="button" wire:click="stopSharing" wire:confirm="Stop sharing? The link stops working."
                        class="rounded-md px-3 py-1.5 text-sm text-red-700 hover:bg-red-50">Stop sharing</button>
            </div>
        @else
            <button type="button" wire:click="newShareLink" class="mt-3 rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Create share link</button>
        @endif
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Export spools to CSV</h2>
        <p class="mt-1 text-sm text-stone-600">
            All your spools, whatever their status, with refill and purchase date. Opens in Excel, Numbers or Google Sheets.
        </p>
        <a href="{{ route('spools.export') }}" class="mt-3 inline-block rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Export CSV ↓</a>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
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

        @if ($importResult)
            @php
                $summary = "{$importResult['created']} added, {$importResult['updated']} updated, {$importResult['unchanged']} unchanged"
                    .($importResult['errors'] ? ', '.count($importResult['errors']).' skipped' : '');
            @endphp
            <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm" role="status">
                <p class="font-medium">Import done: {{ $summary }}. <a href="{{ route('spools') }}" class="underline">See My spools</a></p>
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
    </section>
</div>

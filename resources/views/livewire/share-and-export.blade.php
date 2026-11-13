<div class="max-w-3xl space-y-4">
    <h1 class="text-xl font-semibold">Share &amp; export</h1>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Print request link</h2>
        <p class="mt-1 text-sm text-stone-600">
            People on the list below open this link, sign in and send you a print request: an STL file or a link, and up to
            {{ \App\Models\PrintRequest::MAX_COLORS }} of the colors you have in stock. They see the status of their own requests and your reply there.
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
        <h2 class="font-semibold">Who may request prints</h2>
        <p class="mt-1 text-sm text-stone-600">
            People on this list may send requests through your link. They sign in with SSO or a code sent to their email first, and you
            see their verified email in your <a href="{{ route('print-requests') }}" class="underline">print requests</a>.
            One per line: an email, <code>@domain</code> for everyone at that domain, or <code>*</code> for anyone with a working email.
            Leave it empty to take no print requests.
        </p>
        <form wire:submit="savePrintRequesters" class="mt-3 space-y-2">
            <textarea wire:model="printRequesterEmails" rows="4" aria-label="Who may request prints"
                      placeholder="friend@example.com&#10;@family.example"
                      class="w-full rounded-md border-stone-300 px-2 py-1.5 font-mono text-sm"></textarea>
            @error('printRequesterEmails') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Save</button>
                @if ($printRequestersSaved)
                    <span class="text-sm text-emerald-700" role="status">Saved.</span>
                @endif
            </div>
        </form>

        @if ($accessRequests->isNotEmpty())
            <div id="access-requests" class="mt-4 border-t border-stone-100 pt-4">
                <h3 class="text-sm font-semibold">Access requests <span class="tabular-nums text-stone-500">{{ $accessRequests->count() }}</span></h3>
                <p class="mt-1 text-xs text-stone-500">People who signed in on your link but are not on the list. Approving adds their email and tells them by email.</p>
                <ul class="mt-2 divide-y divide-stone-100">
                    @foreach ($accessRequests as $accessRequest)
                        <li wire:key="access-request-{{ $accessRequest->id }}" class="flex flex-wrap items-start gap-3 py-2 text-sm">
                            <div class="min-w-0 flex-1">
                                <p><span class="font-medium">{{ $accessRequest->name }}</span> <span class="font-mono text-xs text-stone-500">{{ $accessRequest->email }}</span></p>
                                @if ($accessRequest->message)
                                    <p class="mt-0.5 whitespace-pre-line text-stone-600">{{ $accessRequest->message }}</p>
                                @endif
                                <p class="text-xs text-stone-500">{{ $accessRequest->created_at->format('j M Y, H:i') }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" wire:click="approveAccess({{ $accessRequest->id }})"
                                        class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Approve</button>
                                <button type="button" wire:click="declineAccess({{ $accessRequest->id }})" wire:confirm="Decline {{ $accessRequest->email }}?"
                                        class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">Decline</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
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

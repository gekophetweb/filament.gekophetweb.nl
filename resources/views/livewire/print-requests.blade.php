<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Print requests</h1>
            <p class="text-sm text-stone-500">
                Sent by people with your share link.
                @unless ($shareUrl)
                    Sharing is off, so nobody can send new ones. <a href="{{ route('share-export') }}" class="underline">Create a share link</a>
                @endunless
            </p>
        </div>
        <label class="flex items-center gap-2 text-sm" title="{{ $email }}">
            <input type="checkbox" wire:click="toggleEmails" @checked($emailsOn) class="rounded border-stone-300">
            Email me at {{ $email }} for each new request
        </label>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        {{-- Request list --}}
        <section @class(['space-y-2', 'hidden lg:block' => $opened])>
            <nav class="flex gap-1 rounded-lg bg-white p-1 text-sm shadow-sm" aria-label="Folders">
                @foreach (\App\Livewire\PrintRequests::FOLDERS as $folder => $label)
                    <button type="button" wire:click="$set('show', '{{ $folder }}')" @class([
                        'flex-1 rounded-md px-2 py-1',
                        'bg-stone-900 text-white' => $show === $folder,
                        'hover:bg-stone-100' => $show !== $folder,
                    ])>
                        {{ $label }}
                        @if (isset($counts[$folder]) && $counts[$folder])
                            <span class="tabular-nums opacity-70">{{ $counts[$folder] }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>

            <ul class="divide-y divide-stone-100 overflow-hidden rounded-xl bg-white shadow-sm">
                @forelse ($requests as $printRequest)
                    <li wire:key="print-request-{{ $printRequest->uuid }}">
                        <button type="button" wire:click="open('{{ $printRequest->uuid }}')" @class([
                            'flex w-full items-start gap-3 px-3 py-2.5 text-left hover:bg-stone-50',
                            'bg-emerald-50' => $opened?->is($printRequest),
                        ])>
                            <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-emerald-600' => $printRequest->isUnread()])
                                  @if ($printRequest->isUnread()) aria-label="Unread" @endif></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-2">
                                    <span @class(['truncate', 'font-semibold' => $printRequest->isUnread()])>{{ $printRequest->requester_name }}</span>
                                    <span class="shrink-0 text-xs text-stone-500">{{ $printRequest->created_at->diffForHumans(short: true) }}</span>
                                </span>
                                <span class="mt-0.5 flex items-center gap-2 text-xs text-stone-500">
                                    <span class="rounded px-1.5 py-0.5 {{ $printRequest->status->badgeClasses() }}">{{ $printRequest->status->label() }}</span>
                                    <span class="truncate">{{ $printRequest->stl_original_name ?? $printRequest->model_url }}</span>
                                    <span class="ml-auto flex shrink-0 -space-x-1">
                                        @foreach ($printRequest->colors as $color)
                                            <span class="size-3.5 rounded-full ring-1 ring-white" style="background: {{ $color->swatchCss() }}"></span>
                                        @endforeach
                                    </span>
                                </span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="py-12 text-center text-sm text-stone-500">
                        {{ ['open' => 'No open print requests.', 'unread' => 'Nothing unread.', 'all' => 'No print requests yet.'][$show] ?? 'No print requests.' }}
                    </li>
                @endforelse
            </ul>

            <div>{{ $requests->links() }}</div>
        </section>

        {{-- Opened request --}}
        @if ($opened)
            <article wire:key="opened-{{ $opened->uuid }}" class="space-y-4 rounded-xl bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <button type="button" wire:click="close" class="mb-2 text-sm text-stone-500 hover:text-stone-900 lg:hidden">← All requests</button>
                        <h2 class="text-lg font-semibold">{{ $opened->requester_name }}</h2>
                        <p class="text-xs text-stone-500">
                            {{ $opened->created_at->format('j M Y, H:i') }}
                            @if ($opened->requester_contact)
                                ·
                                @if (filter_var($opened->requester_contact, FILTER_VALIDATE_EMAIL))
                                    <a href="mailto:{{ $opened->requester_contact }}" class="underline">{{ $opened->requester_contact }}</a>
                                @else
                                    {{ $opened->requester_contact }}
                                @endif
                            @endif
                        </p>
                    </div>
                    <span class="rounded px-1.5 py-0.5 text-xs {{ $opened->status->badgeClasses() }}">{{ $opened->status->label() }}</span>
                </div>

                <div class="flex flex-wrap gap-3 text-sm">
                    @if ($opened->stl_path)
                        <a href="{{ route('print-requests.stl', $opened) }}" class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">
                            Download {{ $opened->stl_original_name }} ({{ number_format(($opened->stl_size ?? 0) / 1048576, 1) }} MB) ↓
                        </a>
                    @endif
                    @if ($opened->model_url)
                        <a href="{{ $opened->model_url }}" target="_blank" rel="noopener noreferrer nofollow"
                           class="max-w-full truncate rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">{{ $opened->model_url }} ↗</a>
                    @endif
                </div>

                @if ($opened->colors->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($opened->colors as $color)
                            <a href="{{ route('filaments.show', $color) }}" class="flex items-center gap-2 rounded-full border border-stone-300 py-1 pl-1 pr-3 text-sm hover:bg-stone-50">
                                <span class="h-5 w-5 shrink-0 rounded-full ring-1 ring-stone-300" style="background: {{ $color->swatchCss() }}"></span>
                                {{ $color->article_name }} <span class="text-xs text-stone-500">{{ $color->material->name }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-stone-500">No colors picked.</p>
                @endif

                @if ($opened->notes)
                    <p class="whitespace-pre-line text-sm text-stone-700">{{ $opened->notes }}</p>
                @endif

                <form wire:submit="saveComments" class="grid gap-3 border-t border-stone-100 pt-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">Reply to {{ $opened->requester_name }}</span>
                        <textarea wire:model="reply" rows="3" maxlength="2000" placeholder="Ready on Friday, pick it up any time…"
                                  class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5 text-sm"></textarea>
                        <span class="text-xs text-stone-500">They see this with the status on your share link.</span>
                        @error('reply') <span class="block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">Private note</span>
                        <textarea wire:model="privateNote" rows="3" maxlength="2000" placeholder="Only you see this."
                                  class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5 text-sm"></textarea>
                        @error('privateNote') <span class="block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <div class="flex items-center gap-3 sm:col-span-2">
                        <button type="submit" class="rounded-md border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50">Save reply and note</button>
                        <span wire:loading wire:target="saveComments" class="text-sm text-stone-500">Saving…</span>
                        @if ($commentsSaved)
                            <span class="text-sm text-emerald-700" role="status">Saved.</span>
                        @endif
                    </div>
                </form>

                <div class="flex flex-wrap gap-2 border-t border-stone-100 pt-3 text-sm">
                    @if ($opened->status === \App\Enums\PrintRequestStatus::Open)
                        <button type="button" wire:click="setStatus('{{ $opened->uuid }}', 'printed')" class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Mark printed</button>
                        <button type="button" wire:click="setStatus('{{ $opened->uuid }}', 'declined')" class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">Decline</button>
                    @else
                        <button type="button" wire:click="setStatus('{{ $opened->uuid }}', 'open')" class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">Reopen</button>
                    @endif
                    <button type="button" wire:click="markUnread('{{ $opened->uuid }}')" class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">Mark unread</button>
                    <button type="button" wire:click="delete('{{ $opened->uuid }}')" wire:confirm="Delete this request and its STL file?"
                            class="ml-auto rounded-md px-3 py-1.5 text-red-700 hover:bg-red-50">Delete</button>
                </div>
            </article>
        @else
            <p class="hidden place-self-center text-sm text-stone-500 lg:block">Pick a request to read it.</p>
        @endif
    </div>
</div>

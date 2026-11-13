<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold">Suggestions</h1>
        <p class="text-sm text-stone-500">
            Ideas and feedback from the suggestion box on your share link, sent by the people who may request prints.
            @if (! $shareUrl || ! $takesRequests)
                Nobody can send new ones right now. <a href="{{ route('share-export') }}" class="underline">Share &amp; export</a>
            @endif
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        <section @class(['space-y-2', 'hidden lg:block' => $opened])>
            <nav class="flex gap-1 rounded-lg bg-white p-1 text-sm shadow-sm" aria-label="Folders">
                @foreach (\App\Livewire\Suggestions::FOLDERS as $folder => $label)
                    <button type="button" wire:click="$set('show', '{{ $folder }}')" @class([
                        'flex-1 rounded-md px-2 py-1',
                        'bg-stone-900 text-white' => $show === $folder,
                        'hover:bg-stone-100' => $show !== $folder,
                    ])>
                        {{ $label }}
                        @if ($folder === 'unread' && $unreadCount)
                            <span class="tabular-nums opacity-70">{{ $unreadCount }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>

            <ul class="divide-y divide-stone-100 overflow-hidden rounded-xl bg-white shadow-sm">
                @forelse ($suggestions as $suggestion)
                    <li wire:key="suggestion-{{ $suggestion->uuid }}">
                        <button type="button" wire:click="open('{{ $suggestion->uuid }}')" @class([
                            'flex w-full items-start gap-3 px-3 py-2.5 text-left hover:bg-stone-50',
                            'bg-emerald-50' => $opened?->is($suggestion),
                        ])>
                            <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-emerald-600' => $suggestion->isUnread()])
                                  @if ($suggestion->isUnread()) aria-label="Unread" @endif></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-2">
                                    <span @class(['truncate', 'font-semibold' => $suggestion->isUnread()])>{{ $suggestion->requester_name }}</span>
                                    <span class="shrink-0 text-xs text-stone-500">{{ $suggestion->created_at->diffForHumans(short: true) }}</span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-stone-500">{{ \Illuminate\Support\Str::limit($suggestion->body, 80) }}</span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="py-12 text-center text-sm text-stone-500">{{ $show === 'unread' ? 'Nothing unread.' : 'No suggestions yet.' }}</li>
                @endforelse
            </ul>

            <div>{{ $suggestions->links() }}</div>
        </section>

        @if ($opened)
            <article wire:key="opened-{{ $opened->uuid }}" class="space-y-4 rounded-xl bg-white p-4 shadow-sm">
                <div>
                    <button type="button" wire:click="close" class="mb-2 text-sm text-stone-500 hover:text-stone-900 lg:hidden">← All suggestions</button>
                    <h2 class="text-lg font-semibold">{{ $opened->requester_name }}</h2>
                    <p class="text-xs text-stone-500">
                        {{ $opened->created_at->format('j M Y, H:i') }} ·
                        <a href="mailto:{{ $opened->requester_email }}" class="underline">{{ $opened->requester_email }}</a>
                    </p>
                </div>

                <p class="whitespace-pre-line text-sm text-stone-700">{{ $opened->body }}</p>

                <div class="flex flex-wrap gap-2 border-t border-stone-100 pt-3 text-sm">
                    <a href="mailto:{{ $opened->requester_email }}?subject={{ rawurlencode('Re: your suggestion') }}"
                       class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Reply by email</a>
                    <button type="button" wire:click="markUnread('{{ $opened->uuid }}')" class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">Mark unread</button>
                    <button type="button" wire:click="delete('{{ $opened->uuid }}')" wire:confirm="Delete this suggestion?"
                            class="ml-auto rounded-md px-3 py-1.5 text-red-700 hover:bg-red-50">Delete</button>
                </div>
            </article>
        @else
            <p class="hidden place-self-center text-sm text-stone-500 lg:block">Pick a suggestion to read it.</p>
        @endif
    </div>
</div>

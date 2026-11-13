<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold">Suggestions</h1>
        <p class="text-sm text-stone-500">
            Ideas and feedback from the Suggestions tab of your share link, sent by the people who may request prints.
            They see the status and your replies there, and can answer.
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
                        @if (isset($counts[$folder]) && $counts[$folder])
                            <span class="tabular-nums opacity-70">{{ $counts[$folder] }}</span>
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
                                    <span class="shrink-0 text-xs text-stone-500">{{ $suggestion->updated_at->diffForHumans(short: true) }}</span>
                                </span>
                                <span class="mt-0.5 flex items-center gap-2 text-xs text-stone-500">
                                    <span class="rounded px-1.5 py-0.5 {{ $suggestion->status->badgeClasses() }}">{{ $suggestion->status->label() }}</span>
                                    <span class="truncate">{{ \Illuminate\Support\Str::limit($suggestion->body, 80) }}</span>
                                    @if ($suggestion->messages_count)
                                        <span class="ml-auto shrink-0" title="Messages">💬 {{ $suggestion->messages_count }}</span>
                                    @endif
                                </span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="py-12 text-center text-sm text-stone-500">
                        {{ ['open' => 'No open suggestions.', 'unread' => 'Nothing unread.', 'all' => 'No suggestions yet.'][$show] ?? 'No suggestions.' }}
                    </li>
                @endforelse
            </ul>

            <div>{{ $suggestions->links() }}</div>
        </section>

        @if ($opened)
            <article wire:key="opened-{{ $opened->uuid }}" class="space-y-4 rounded-xl bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <button type="button" wire:click="close" class="mb-2 text-sm text-stone-500 hover:text-stone-900 lg:hidden">← All suggestions</button>
                        <h2 class="text-lg font-semibold">{{ $opened->requester_name }}</h2>
                        <p class="text-xs text-stone-500">
                            {{ $opened->created_at->format('j M Y, H:i') }} ·
                            <a href="mailto:{{ $opened->requester_email }}" class="underline">{{ $opened->requester_email }}</a>
                        </p>
                    </div>
                    <span class="rounded px-1.5 py-0.5 text-xs {{ $opened->status->badgeClasses() }}">{{ $opened->status->label() }}</span>
                </div>

                <p class="whitespace-pre-line text-sm text-stone-700">{{ $opened->body }}</p>

                @foreach ($opened->messages as $entry)
                    <div wire:key="message-{{ $entry->id }}" @class([
                        'whitespace-pre-line rounded-md px-3 py-2 text-sm',
                        'ml-6 bg-emerald-50 text-stone-800' => $entry->from_owner,
                        'bg-stone-50 text-stone-700' => ! $entry->from_owner,
                    ])>
                        <span class="font-medium">{{ $entry->from_owner ? 'You' : $opened->requester_name }}</span>
                        <span class="text-xs text-stone-500">· {{ $entry->created_at->format('j M, H:i') }}</span>
                        <span class="block">{{ $entry->body }}</span>
                    </div>
                @endforeach

                <form wire:submit="sendReply" class="space-y-2 border-t border-stone-100 pt-3">
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">Reply to {{ $opened->requester_name }}</span>
                        <textarea wire:model="reply" rows="3" maxlength="{{ \App\Models\Suggestion::MAX_LENGTH }}" placeholder="Good idea, I'll get it next time…"
                                  class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5 text-sm"></textarea>
                        <span class="text-xs text-stone-500">They see it on your share link, can answer there, and get it by email.</span>
                        @error('reply') <span class="block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Send reply</button>
                        <span wire:loading wire:target="sendReply" class="text-sm text-stone-500">Sending…</span>
                        @if ($replyEmailedTo)
                            <span class="text-sm text-emerald-700" role="status">Sent, and emailed to {{ $replyEmailedTo }}.</span>
                        @endif
                    </div>
                </form>

                <div class="flex flex-wrap gap-2 border-t border-stone-100 pt-3 text-sm">
                    @foreach (\App\Enums\SuggestionStatus::cases() as $status)
                        @unless ($opened->status === $status)
                            <button type="button" wire:key="status-{{ $status->value }}" wire:click="setStatus('{{ $opened->uuid }}', '{{ $status->value }}')"
                                    class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">
                                {{ $status === \App\Enums\SuggestionStatus::Open ? 'Reopen' : 'Mark '.strtolower($status->label()) }}
                            </button>
                        @endunless
                    @endforeach
                    <button type="button" wire:click="markUnread('{{ $opened->uuid }}')" class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50">Mark unread</button>
                    <button type="button" wire:click="delete('{{ $opened->uuid }}')" wire:confirm="Delete this suggestion and its conversation?"
                            class="ml-auto rounded-md px-3 py-1.5 text-red-700 hover:bg-red-50">Delete</button>
                </div>
            </article>
        @else
            <p class="hidden place-self-center text-sm text-stone-500 lg:block">Pick a suggestion to read it.</p>
        @endif
    </div>
</div>

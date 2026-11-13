@extends('layouts.app', ['title' => "Suggestions for {$owner->name}"])

@section('content')
    <div class="max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold">Suggestions for {{ $owner->name }}</h1>

        @if (! $owner->takesPrintRequests())
            <section class="rounded-xl bg-white p-4 text-sm text-stone-600 shadow-sm">
                {{ $owner->name }} is not taking suggestions right now.
            </section>
        @else
            <x-share-tabs :owner="$owner" />

            @if ($ownSuggestions->isNotEmpty())
                <section class="rounded-xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">Your suggestions</h2>
                    <ul class="mt-2 divide-y divide-stone-100">
                        @foreach ($ownSuggestions as $suggestion)
                            <li id="suggestion-{{ $suggestion->uuid }}" class="space-y-2 py-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded px-1.5 py-0.5 text-xs {{ $suggestion->status->badgeClasses() }}">{{ $suggestion->status->label() }}</span>
                                    <span class="text-xs text-stone-500">{{ $suggestion->created_at->format('j M Y') }}</span>
                                </div>
                                <p class="whitespace-pre-line text-stone-800">{{ $suggestion->body }}</p>

                                @foreach ($suggestion->messages as $entry)
                                    <div @class([
                                        'whitespace-pre-line rounded-md px-2 py-1.5',
                                        'bg-emerald-50 text-stone-800' => $entry->from_owner,
                                        'ml-6 bg-stone-50 text-stone-700' => ! $entry->from_owner,
                                    ])>
                                        <span class="font-medium">{{ $entry->from_owner ? $owner->name : 'You' }}</span>
                                        <span class="text-xs text-stone-500">· {{ $entry->created_at->format('j M, H:i') }}</span>
                                        <span class="block">{{ $entry->body }}</span>
                                    </div>
                                @endforeach

                                @if (session('answer_sent') === $suggestion->uuid)
                                    <p class="text-xs text-emerald-700" role="status">Your answer was sent.</p>
                                @endif

                                @if ($suggestion->messages->contains('from_owner', true))
                                    <form method="POST" action="{{ route('share.suggestions.answer', [$owner->share_token, $suggestion->uuid]) }}" class="space-y-1">
                                        @csrf
                                        <textarea name="body" rows="2" required maxlength="{{ \App\Models\Suggestion::MAX_LENGTH }}"
                                                  aria-label="Answer {{ $owner->name }}" placeholder="Answer {{ $owner->name }}…"
                                                  class="w-full rounded-md border-stone-300 px-2 py-1.5 text-sm"></textarea>
                                        @error('body', "answer-{$suggestion->uuid}") <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                                        <button type="submit" class="rounded-md border border-stone-300 px-3 py-1 text-sm hover:bg-stone-50">Send answer</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="rounded-xl bg-white p-4 shadow-sm">
                <h2 class="font-semibold">{{ $ownSuggestions->isNotEmpty() ? 'New suggestion' : 'Suggestion box' }}</h2>
                <p class="mt-1 text-sm text-stone-600">
                    An idea, a color {{ $owner->name }} should get, or other feedback that is not a print request.
                    You see what {{ $owner->name }} does with it here, and can answer their replies.
                </p>

                @if (session('suggestion_sent'))
                    <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm" role="status">Thanks, your suggestion was sent.</div>
                @endif

                @include('share.requester-access', ['page' => 'suggestions'])

                @if ($isAllowed)
                    <form method="POST" action="{{ route('share.suggestions.store', $owner->share_token) }}" class="mt-3 space-y-2">
                        @csrf
                        <textarea name="body" rows="3" required maxlength="{{ \App\Models\Suggestion::MAX_LENGTH }}" aria-label="Your suggestion"
                                  placeholder="It would be great if…"
                                  class="w-full rounded-md border-stone-300 px-2 py-1.5 text-sm">{{ old('body') }}</textarea>
                        @error('body', 'suggestion') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                        <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Send suggestion</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
@endsection

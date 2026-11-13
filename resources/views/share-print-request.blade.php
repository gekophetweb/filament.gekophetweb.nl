@extends('layouts.app', ['title' => "Request a print from {$owner->name}"])

@section('content')
    <div class="max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold">Request a print from {{ $owner->name }}</h1>

        @if (! $owner->takesPrintRequests())
            <section class="rounded-xl bg-white p-4 text-sm text-stone-600 shadow-sm">
                {{ $owner->name }} is not taking print requests right now.
            </section>
        @else
        <section class="rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm text-stone-600">
                Upload an STL file or paste a link to the model (Printables, MakerWorld, Thingiverse…) and pick up to
                {{ \App\Models\PrintRequest::MAX_COLORS }} colors. {{ $owner->name }} sees your request and gets back to you.
            </p>

            @if (session('print_request_sent'))
                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm" role="status">
                    Thanks, your print request was sent. You can follow it under Your requests.
                </div>
            @endif

            @if (session('print_requester_error'))
                <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                    {{ session('print_requester_error') }}
                </div>
            @endif

            @if ($requester)
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-stone-600">
                    <span>Signed in as <strong class="text-stone-900">{{ $requester->email }}</strong></span>
                    @guest
                        <form method="POST" action="{{ route('share.sign-out', $owner->share_token) }}">
                            @csrf
                            <button type="submit" class="underline hover:text-stone-900">Sign out</button>
                        </form>
                    @endguest
                </div>
            @endif

            @if (! $requester)
                @php($pendingEmail = \App\Http\Controllers\Auth\PrintRequesterEmailCodeController::pendingEmail())
                <div class="mt-3 space-y-3 rounded-lg border border-stone-200 bg-stone-50 p-3 text-sm">
                    <p>{{ $owner->name }} only takes requests from people they know. Sign in, or get a code by email, so they can see who you are.</p>

                    @if ($pendingEmail)
                        <form method="POST" action="{{ route('share.email-code.verify', $owner->share_token) }}" class="space-y-2">
                            @csrf
                            <p>
                                If {{ $owner->name }} takes requests from <strong>{{ $pendingEmail }}</strong>,
                                a {{ \App\Http\Controllers\Auth\PrintRequesterEmailCodeController::CODE_LENGTH }}-digit code is on its way there.
                                It works for {{ \App\Http\Controllers\Auth\PrintRequesterEmailCodeController::CODE_MINUTES }} minutes.
                            </p>
                            <div class="flex flex-wrap items-center gap-2">
                                <input name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="12"
                                       aria-label="Code from the email" placeholder="123456"
                                       class="w-32 rounded-md border-stone-300 px-2 py-1.5 font-mono tracking-widest">
                                <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Continue</button>
                            </div>
                            @error('code', 'emailCode') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                        </form>
                        <form method="POST" action="{{ route('share.email-code.cancel', $owner->share_token) }}">
                            @csrf
                            <button type="submit" class="text-stone-600 underline hover:text-stone-900">Use another email</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('share.email-code', $owner->share_token) }}" class="space-y-2">
                            @csrf
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="block">
                                    <span class="text-xs font-medium text-stone-500">Your name</span>
                                    <input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"
                                           class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
                                    @error('name', 'emailCode') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                                </label>
                                <label class="block">
                                    <span class="text-xs font-medium text-stone-500">Your email</span>
                                    <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"
                                           class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
                                    @error('email', 'emailCode') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                                </label>
                            </div>
                            <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Email me a code</button>
                        </form>
                        <p class="text-stone-600">
                            Or <a href="{{ route('share.sign-in', $owner->share_token) }}" class="font-medium underline hover:text-stone-900">sign in with SSO</a>.
                        </p>
                    @endif
                </div>
            @elseif (! $isAllowed)
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm" role="alert">
                    {{ $owner->name }} does not take print requests from {{ $requester->email }}. Ask them to add you, or sign in with another account.
                </div>
            @else
            <form method="POST" action="{{ route('share.print-requests.store', $owner->share_token) }}" enctype="multipart/form-data"
                  class="mt-3 space-y-4"
                  x-data="{ picked: @js(array_map('strval', (array) old('colors', []))), max: {{ \App\Models\PrintRequest::MAX_COLORS }} }">
                @csrf

                <label class="block sm:max-w-sm">
                    <span class="text-xs font-medium text-stone-500">Your name</span>
                    <input name="requester_name" value="{{ old('requester_name', $requester->name) }}" required maxlength="100"
                           class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
                    @error('requester_name') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">STL file (at most {{ config('access.print_request_max_upload_mb') }} MB)</span>
                        <input type="file" name="stl" accept=".stl"
                               class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-stone-200 file:px-3 file:py-1.5 file:text-sm hover:file:bg-stone-300">
                        @error('stl') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">…or a link to the model</span>
                        <input type="url" name="model_url" value="{{ old('model_url') }}" maxlength="2048" placeholder="https://"
                               class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
                        @error('model_url') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                </div>

                @if ($products->isNotEmpty())
                    <fieldset>
                        <legend class="text-xs font-medium text-stone-500">
                            Colors (up to {{ \App\Models\PrintRequest::MAX_COLORS }}) · <span x-text="`${picked.length} picked`">0 picked</span>
                        </legend>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach ($products as $product)
                                <label class="flex cursor-pointer items-center gap-2 rounded-full border border-stone-300 py-1 pl-1 pr-3 text-sm has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50">
                                    <input type="checkbox" name="colors[]" value="{{ $product->id }}" x-model="picked" class="sr-only"
                                           :disabled="picked.length >= max && ! picked.includes('{{ $product->id }}')">
                                    <span class="h-5 w-5 shrink-0 rounded-full ring-1 ring-stone-300" style="background: {{ $product->swatchCss() }}"></span>
                                    {{ $product->article_name }} <span class="text-xs text-stone-500">{{ $product->material->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('colors') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                        @error('colors.*') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                    </fieldset>
                @endif

                <label class="block">
                    <span class="text-xs font-medium text-stone-500">Notes (optional)</span>
                    <textarea name="notes" rows="3" maxlength="2000" placeholder="Size, how many, which part in which color…"
                              class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">{{ old('notes') }}</textarea>
                    @error('notes') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                </label>

                <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Send request</button>
                <p class="text-xs text-stone-500">{{ $owner->name }} replies to {{ $requester->email }}.</p>
            </form>
            @endif
        </section>

        @if ($ownRequests->isNotEmpty())
            <section class="rounded-xl bg-white p-4 shadow-sm">
                <h2 class="font-semibold">Your requests</h2>
                <ul class="mt-2 divide-y divide-stone-100">
                    @foreach ($ownRequests as $printRequest)
                        <li class="space-y-1 py-2.5 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded px-1.5 py-0.5 text-xs {{ $printRequest->status->badgeClasses() }}">{{ $printRequest->status->label() }}</span>
                                <span class="min-w-0 flex-1 truncate">{{ $printRequest->stl_original_name ?? $printRequest->model_url }}</span>
                                <span class="flex shrink-0 -space-x-1">
                                    @foreach ($printRequest->colors as $color)
                                        <span class="size-3.5 rounded-full ring-1 ring-white" style="background: {{ $color->swatchCss() }}" title="{{ $color->article_name }}"></span>
                                    @endforeach
                                </span>
                                <span class="shrink-0 text-xs text-stone-500">{{ $printRequest->created_at->format('j M Y') }}</span>
                            </div>
                            @if ($printRequest->stl_volume)
                                <x-filament-estimate :print-request="$printRequest" class="text-xs" />
                            @endif
                            @if ($printRequest->reply)
                                <p class="whitespace-pre-line rounded-md bg-stone-50 px-2 py-1.5 text-stone-700">
                                    <span class="font-medium">{{ $owner->name }}:</span> {{ $printRequest->reply }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($isAllowed)
            <section id="suggestion-box" class="rounded-xl bg-white p-4 shadow-sm">
                <h2 class="font-semibold">Suggestion box</h2>
                <p class="mt-1 text-sm text-stone-600">An idea, a color {{ $owner->name }} should get, or other feedback that is not a print request.</p>

                @if (session('suggestion_sent'))
                    <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm" role="status">Thanks, your suggestion was sent.</div>
                @endif

                <form method="POST" action="{{ route('share.suggestions.store', $owner->share_token) }}" class="mt-3 space-y-2">
                    @csrf
                    <textarea name="body" rows="3" required maxlength="{{ \App\Models\Suggestion::MAX_LENGTH }}" aria-label="Your suggestion"
                              placeholder="It would be great if…"
                              class="w-full rounded-md border-stone-300 px-2 py-1.5 text-sm">{{ old('body') }}</textarea>
                    @error('body', 'suggestion') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-sm text-white hover:bg-stone-700">Send suggestion</button>
                </form>
            </section>
        @endif
        @endif
    </div>
@endsection

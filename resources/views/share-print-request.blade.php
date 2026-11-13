@extends('layouts.app', ['title' => "Request a print from {$owner->name}"])

@section('content')
    <div class="max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold">Request a print from {{ $owner->name }}</h1>
        @if ($owner->takesPrintRequests())
            <x-share-tabs :owner="$owner" />
        @endif

        @if (! $owner->takesPrintRequests())
            <section class="rounded-xl bg-white p-4 text-sm text-stone-600 shadow-sm">
                {{ $owner->name }} is not taking print requests right now.
            </section>
        @else
        @if (session('print_request_deleted'))
            <div class="rounded-lg border border-stone-200 bg-white p-3 text-sm shadow-sm" role="status">Your request was deleted.</div>
        @endif

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
                                <form method="POST" action="{{ route('share.print-requests.destroy', [$owner->share_token, $printRequest]) }}"
                                      x-data x-on:submit="confirm({{ \Illuminate\Support\Js::from("Delete this request? {$owner->name} no longer sees it.") }}) || $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="shrink-0 rounded px-1.5 py-0.5 text-xs text-red-700 hover:bg-red-50">Delete</button>
                                </form>
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

        <section class="rounded-xl bg-white p-4 shadow-sm">
            @if ($ownRequests->isNotEmpty())
                <h2 class="mb-1 font-semibold">New request</h2>
            @endif
            <p class="text-sm text-stone-600">
                Upload an STL file or paste a link to the model (Printables, MakerWorld, Thingiverse…) and pick up to
                {{ \App\Models\PrintRequest::MAX_COLORS }} colors. {{ $owner->name }} sees your request and gets back to you.
            </p>

            @if (session('print_request_sent'))
                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm" role="status">
                    Thanks, your print request was sent. You can follow it under Your requests above.
                </div>
            @endif

            @include('share.requester-access', ['page' => ''])

            @if ($isAllowed)
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
                    <div x-data="stlEstimate(@js([
                        'fillLow' => \App\Support\FilamentEstimate::FILL_LOW,
                        'fillHigh' => \App\Support\FilamentEstimate::FILL_HIGH,
                        'density' => \App\Support\FilamentEstimate::densityOf('PLA'),
                        'material' => 'PLA',
                        'spoolPrice' => (float) config('access.print_request_spool_price'),
                        'spoolGrams' => \App\Support\FilamentEstimate::SPOOL_GRAMS,
                    ]))">
                        <label class="block">
                            <span class="text-xs font-medium text-stone-500">STL file (at most {{ config('access.print_request_max_upload_mb') }} MB)</span>
                            <input type="file" name="stl" accept=".stl" x-on:change="estimate($event.target.files[0])"
                                   class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-stone-200 file:px-3 file:py-1.5 file:text-sm hover:file:bg-stone-300">
                            @error('stl') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                        </label>
                        <p x-show="busy" x-cloak class="mt-1 text-xs text-stone-500">Measuring the STL…</p>
                        <p x-show="failed" x-cloak class="mt-1 text-xs text-amber-800">This file could not be read as an STL, so there is no estimate.</p>
                        <div x-show="result" x-cloak class="mt-2 rounded-md bg-stone-50 px-2 py-1.5 text-sm text-stone-600" role="status">
                            <span class="font-medium text-stone-900" x-text="result?.grams"></span>
                            · <span class="font-medium text-stone-900" x-text="result?.price"></span>
                            · <span x-text="result?.size"></span>
                            <span class="block text-xs text-stone-500">
                                Estimate from the STL in PLA, without supports, at €{{ number_format((float) config('access.print_request_spool_price'), 2) }} per 1 kg spool.
                            </span>
                            <span x-show="result?.misScaled" class="mt-1 block text-xs text-amber-800">This size looks off; the STL may not be in millimetres, so the estimate is too.</span>
                        </div>
                    </div>
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
        @endif
    </div>
@endsection

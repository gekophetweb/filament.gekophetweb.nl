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

        <section id="print-request" class="max-w-3xl rounded-xl bg-white p-4 shadow-sm"
                 @if ($errors->any()) x-data x-init="$el.scrollIntoView()" @endif>
            <h2 class="font-semibold">Request a print</h2>
            <p class="mt-1 text-sm text-stone-600">
                Upload an STL file or paste a link to the model (Printables, MakerWorld, Thingiverse…) and pick up to
                {{ \App\Models\PrintRequest::MAX_COLORS }} colors. {{ $owner->name }} sees your request and gets back to you.
            </p>

            @if (session('print_request_sent'))
                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm" role="status">
                    Thanks, your print request was sent.
                </div>
            @endif

            <form method="POST" action="{{ route('share.print-requests.store', $owner->share_token) }}" enctype="multipart/form-data"
                  class="mt-3 space-y-4"
                  x-data="{ picked: @js(array_map('strval', (array) old('colors', []))), max: {{ \App\Models\PrintRequest::MAX_COLORS }} }">
                @csrf

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">Your name</span>
                        <input name="requester_name" value="{{ old('requester_name') }}" required maxlength="100"
                               class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
                        @error('requester_name') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">How to reach you (optional)</span>
                        <input name="requester_contact" value="{{ old('requester_contact') }}" maxlength="255" placeholder="Email or phone"
                               class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
                        @error('requester_contact') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                </div>

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
            </form>
        </section>
    </div>
@endsection

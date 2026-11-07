@extends('layouts.app', ['title' => $spec['info']['title']])

@php
    $md = fn (?string $text) => new \Illuminate\Support\HtmlString(\Illuminate\Support\Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    $methodColors = [
        'GET' => 'bg-sky-100 text-sky-800',
        'POST' => 'bg-emerald-100 text-emerald-800',
        'PATCH' => 'bg-amber-100 text-amber-800',
        'DELETE' => 'bg-rose-100 text-rose-800',
    ];
    $baseUrl = $spec['servers'][0]['url'];
    $json = fn (mixed $value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp

@section('content')
    <div class="grid gap-8 lg:grid-cols-[14rem_1fr]">
        <nav class="hidden lg:block" aria-label="API sections">
            <div class="sticky top-6 space-y-4 text-sm">
                <a href="#start" class="block font-semibold">Getting started</a>
                @foreach ($sections as $tag => $operations)
                    <div>
                        <a href="#{{ Str::slug($tag) }}" class="block font-semibold">{{ $tag }}</a>
                        <ul class="mt-1 space-y-1 border-l border-stone-200 pl-3">
                            @foreach ($operations as $operation)
                                <li><a href="#{{ $operation['id'] }}" class="text-stone-600 hover:text-stone-900">{{ $operation['summary'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <a href="#errors" class="block font-semibold">Errors</a>
            </div>
        </nav>

        <div class="min-w-0 space-y-10">
            <header id="start" class="space-y-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ $spec['info']['title'] }}</h1>
                    <p class="mt-1 max-w-2xl text-stone-600">{{ $spec['info']['description'] }}</p>
                </div>

                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <dl class="grid gap-3 text-sm sm:grid-cols-[9rem_1fr]">
                        <dt class="text-stone-500">Base URL</dt>
                        <dd class="break-all font-mono">{{ $baseUrl }}</dd>
                        <dt class="text-stone-500">Format</dt>
                        <dd>JSON in and out. Send <code class="font-mono">Accept: application/json</code>.</dd>
                        <dt class="text-stone-500">Authentication</dt>
                        <dd><code class="font-mono">Authorization: Bearer &lt;token&gt;</code>, with a token from the Devices page or from <a href="#pairDevice" class="text-emerald-700 underline">pairing by QR code</a>.</dd>
                        <dt class="text-stone-500">Abilities</dt>
                        <dd>Read-only tokens have <code class="font-mono">spools:read</code>. Read-write tokens also have <code class="font-mono">spools:write</code>.</dd>
                        <dt class="text-stone-500">OpenAPI</dt>
                        <dd><a href="{{ route('docs.api.openapi') }}" class="text-emerald-700 underline">openapi.json</a> (OpenAPI {{ $spec['openapi'] }}), for code generators and API clients.</dd>
                    </dl>
                </div>

                <div>
                    <h2 class="mb-2 text-sm font-semibold">Try it</h2>
                    <pre class="overflow-x-auto rounded-xl bg-stone-900 p-4 text-xs text-stone-100"><code>curl {{ $baseUrl }}/spools?status=on_spool \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"</code></pre>
                </div>

                <div>
                    <h2 class="mb-2 text-sm font-semibold">Limits</h2>
                    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                        <table class="w-full text-sm">
                            <tbody>
                                @foreach ($limits as $label => $limit)
                                    <tr class="border-t border-stone-100 first:border-t-0">
                                        <th scope="row" class="w-40 px-3 py-2 text-left font-medium">{{ $label }}</th>
                                        <td class="px-3 py-2 text-stone-600">{{ $limit }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-stone-500">Lists are paginated: follow <code class="font-mono">links.next</code> until it is null.</p>
                </div>
            </header>

            @foreach ($sections as $tag => $operations)
                <section id="{{ Str::slug($tag) }}" class="space-y-4" aria-labelledby="{{ Str::slug($tag) }}-title">
                    <div>
                        <h2 id="{{ Str::slug($tag) }}-title" class="text-xl font-semibold">{{ $tag }}</h2>
                        <p class="text-sm text-stone-500">{{ \App\Support\ApiDocumentation::TAGS[$tag] }}</p>
                    </div>

                    @foreach ($operations as $operation)
                        <article id="{{ $operation['id'] }}" class="scroll-mt-6 space-y-4 rounded-xl bg-white p-4 shadow-sm">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded px-2 py-0.5 font-mono text-xs font-semibold {{ $methodColors[$operation['method']] }}">{{ $operation['method'] }}</span>
                                    <code class="break-all font-mono text-sm font-semibold">{{ $operation['path'] }}</code>
                                    @if ($operation['public'])
                                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">No token</span>
                                    @elseif ($operation['ability'])
                                        <span class="rounded-full bg-stone-100 px-2 py-0.5 font-mono text-xs text-stone-600">{{ $operation['ability'] }}</span>
                                    @else
                                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">Any token</span>
                                    @endif
                                </div>
                                <h3 class="font-semibold">{{ $operation['summary'] }}</h3>
                                @if ($operation['description'])
                                    <p class="max-w-3xl text-sm text-stone-600">{{ $md($operation['description']) }}</p>
                                @endif
                            </div>

                            @if ($operation['parameters'])
                                <x-api-fields title="Parameters" :fields="collect($operation['parameters'])->map(fn ($p) => [
                                    'name' => $p['name'],
                                    'where' => $p['in'],
                                    'type' => $docs->typeLabel($p['schema']),
                                    'required' => $p['required'] ?? false,
                                    'description' => $md($p['description']),
                                ])->all()" />
                            @endif

                            @if ($operation['body'])
                                <x-api-fields title="JSON body" :fields="collect($operation['body'])->map(fn ($f) => [...$f, 'where' => null, 'description' => $md($f['description'])])->all()" />
                            @endif

                            <div>
                                <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Responses</h4>
                                <ul class="space-y-1 text-sm">
                                    @foreach ($operation['responses'] as $response)
                                        <li class="flex gap-3">
                                            <span @class(['w-10 shrink-0 font-mono font-semibold', 'text-emerald-700' => $response['status'] < 300, 'text-rose-700' => $response['status'] >= 400])>{{ $response['status'] }}</span>
                                            <span class="text-stone-600">{{ $md($response['description']) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @php($success = collect($operation['responses'])->first(fn ($r) => $r['status'] < 300 && $r['example'] !== null))
                                @if ($success)
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-sm text-emerald-700">Example {{ $success['status'] }} response</summary>
                                        <pre class="mt-2 max-h-96 overflow-auto rounded-lg bg-stone-900 p-3 text-xs text-stone-100"><code>{{ $json($success['example']) }}</code></pre>
                                    </details>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </section>
            @endforeach

            <section id="errors" class="space-y-4" aria-labelledby="errors-title">
                <div>
                    <h2 id="errors-title" class="text-xl font-semibold">Errors</h2>
                    <p class="text-sm text-stone-500">Errors are JSON with a <code class="font-mono">message</code>. A blocked IP address gets a plain-text 403 before anything else runs.</p>
                </div>
                <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="border-b border-stone-200 text-left text-xs text-stone-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">Status</th>
                                <th class="px-3 py-2 font-medium">Meaning</th>
                                <th class="px-3 py-2 font-medium">Example body</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (['401' => 'Unauthenticated', '403' => 'MissingAbility', '404' => 'NotFound', '422' => 'ValidationFailed', '429' => 'TooManyRequests'] as $status => $name)
                                @php($error = $spec['components']['responses'][$name])
                                <tr class="border-t border-stone-100 align-top">
                                    <td class="px-3 py-2 font-mono font-semibold text-rose-700">{{ $status }}</td>
                                    <td class="px-3 py-2 text-stone-600">{{ $md($error['description']) }}</td>
                                    <td class="px-3 py-2"><pre class="overflow-x-auto font-mono text-xs">{{ $json($error['content']['application/json']['example']) }}</pre></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
@endsection

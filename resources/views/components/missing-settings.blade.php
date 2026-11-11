{{-- Banner for signed-in users while the installation lacks settings it needs (see MissingSettings). --}}
@php($missing = app(\App\Support\Setup\MissingSettings::class)->missing())
@if ($missing)
    <div class="border-b border-amber-200 bg-amber-50 text-sm text-amber-900" role="status">
        <div class="mx-auto max-w-7xl px-4 py-2">
            <p class="font-medium">Some settings are missing:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach ($missing as $item)
                    <li>{{ $item['message'] }}</li>
                @endforeach
            </ul>
            <p class="mt-1">
                On the server, run <code class="rounded bg-amber-100 px-1 font-mono text-xs">docker compose exec app {{ collect($missing)->pluck('command')->unique()->join(' / ') }}</code>
                and open the link it prints on the Docker host.
            </p>
        </div>
    </div>
@endif

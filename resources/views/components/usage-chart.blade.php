@props(['months', 'label' => 'Spools added and used up per month'])
@php
    // Grouped bars per month: added (blue) and used up (orange). Colors validated for
    // color-blind separation and >= 3:1 contrast on white; identity also comes from the legend.
    $series = ['added' => ['Added', '#2a78d6'], 'used_up' => ['Used up', '#eb6834']];
    $width = 800; $height = 200; $left = 28; $bottom = 22; $top = 8;
    $plotHeight = $height - $bottom - $top;
    $max = max(1, ...array_map(fn ($m) => max($m['added'], $m['used_up']), $months));
    $step = max(1, (int) ceil($max / 4));
    $yMax = $step * (int) ceil($max / $step);
    $groupWidth = ($width - $left) / max(1, count($months));
    $barWidth = min(18, ($groupWidth - 10) / 2);
    $y = fn (int $value) => $top + $plotHeight - ($value / $yMax) * $plotHeight;
    $isEmpty = collect($months)->every(fn ($m) => $m['added'] === 0 && $m['used_up'] === 0);
@endphp

<figure {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <div class="flex flex-wrap items-center gap-4 text-xs text-stone-600" aria-hidden="true">
        @foreach ($series as [$name, $color])
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $color }}"></span>{{ $name }}</span>
        @endforeach
    </div>

    @if ($isEmpty)
        <p class="py-8 text-center text-sm text-stone-500">No spools added or used up in this period yet.</p>
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full" role="img" aria-label="{{ $label }}">
            @for ($tick = 0; $tick <= $yMax; $tick += $step)
                <line x1="{{ $left }}" x2="{{ $width }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="{{ $tick === 0 ? '#a8a29e' : '#e7e5e4' }}" />
                <text x="{{ $left - 6 }}" y="{{ $y($tick) + 3 }}" text-anchor="end" font-size="10" fill="#78716c">{{ $tick }}</text>
            @endfor

            @foreach ($months as $i => $month)
                @php $x = $left + $i * $groupWidth + ($groupWidth - 2 * $barWidth - 2) / 2; @endphp
                <g wire:key="usage-{{ $month['month'] }}">
                    <title>{{ \Illuminate\Support\Carbon::parse($month['month'].'-01')->format('F Y') }}: {{ $month['added'] }} added, {{ $month['used_up'] }} used up</title>
                    {{-- Hit target covers the whole month, bigger than the bars. --}}
                    <rect x="{{ $left + $i * $groupWidth }}" y="{{ $top }}" width="{{ $groupWidth }}" height="{{ $plotHeight }}" fill="transparent" class="hover:fill-stone-100" />
                    @foreach (array_keys($series) as $s => $key)
                        @if ($month[$key] > 0)
                            @php $barTop = $y($month[$key]); $barX = $x + $s * ($barWidth + 2); @endphp
                            <path fill="{{ $series[$key][1] }}"
                                  d="M{{ $barX }},{{ $y(0) }} V{{ $barTop + 3 }} Q{{ $barX }},{{ $barTop }} {{ $barX + 3 }},{{ $barTop }} H{{ $barX + $barWidth - 3 }} Q{{ $barX + $barWidth }},{{ $barTop }} {{ $barX + $barWidth }},{{ $barTop + 3 }} V{{ $y(0) }} Z" />
                        @endif
                    @endforeach
                    <text x="{{ $left + ($i + 0.5) * $groupWidth }}" y="{{ $height - 6 }}" text-anchor="middle" font-size="10" fill="#78716c">{{ $month['label'] }}</text>
                </g>
            @endforeach
        </svg>
    @endif

    <details class="text-sm">
        <summary class="cursor-pointer text-xs text-stone-500">Show as table</summary>
        <table class="mt-2 w-full text-left text-sm">
            <thead class="text-xs text-stone-500"><tr><th class="py-1">Month</th><th>Added</th><th>Used up</th></tr></thead>
            <tbody>
            @foreach ($months as $month)
                <tr wire:key="usage-row-{{ $month['month'] }}" class="border-t border-stone-100">
                    <td class="py-1">{{ \Illuminate\Support\Carbon::parse($month['month'].'-01')->format('F Y') }}</td>
                    <td>{{ $month['added'] }}</td>
                    <td>{{ $month['used_up'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </details>
</figure>

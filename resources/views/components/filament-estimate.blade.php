@props(['printRequest'])
{{-- The estimated filament for a request's STL, with its size; a hint when the units look wrong. --}}
@php($estimate = $printRequest->filamentEstimate())
@if ($estimate)
    <p {{ $attributes->class('text-sm text-stone-600') }}>
        <span class="font-medium text-stone-900">{{ $estimate->label() }}</span>
        · {{ $estimate->priceLabel() }}
        · {{ $printRequest->dimensionsLabel() }}
        <span class="text-xs text-stone-500">(estimate from the STL, without supports)</span>
        @if ($printRequest->looksMisScaled())
            <span class="mt-1 block text-xs text-amber-800">This size looks off; the STL may not be in millimetres, so the estimate is too.</span>
        @endif
    </p>
@elseif ($printRequest->stl_path === null)
    <p {{ $attributes->class('text-xs text-stone-500') }}>No estimate: there is only a link, no STL file.</p>
@endif

@props(['product', 'size' => 'h-24'])
<div {{ $attributes->merge(['class' => "flex items-end rounded-t-xl p-3 {$size}"]) }}
     style="background: {{ $product->swatchCss() }}; color: {{ $product->labelColor() }}">
    <span class="font-mono text-xs opacity-80">{{ $product->color_hex }}@if ($product->color_hex_2) / {{ $product->color_hex_2 }}@endif</span>
</div>

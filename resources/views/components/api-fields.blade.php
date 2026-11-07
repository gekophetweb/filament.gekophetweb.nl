@props(['title', 'fields'])
<div>
    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $title }}</h4>
    <div class="overflow-x-auto rounded-lg border border-stone-200">
        <table class="w-full text-sm">
            <tbody>
                @foreach ($fields as $field)
                    <tr class="border-t border-stone-100 align-top first:border-t-0">
                        <td class="whitespace-nowrap px-3 py-2">
                            <code class="font-mono font-semibold">{{ $field['name'] }}</code>
                            @if ($field['required'])
                                <span class="ml-1 text-xs text-rose-700">required</span>
                            @endif
                            @if ($field['where'])
                                <span class="block text-xs text-stone-500">{{ $field['where'] }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 font-mono text-xs text-stone-600">{{ $field['type'] }}</td>
                        <td class="px-3 py-2 text-stone-600">{{ $field['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

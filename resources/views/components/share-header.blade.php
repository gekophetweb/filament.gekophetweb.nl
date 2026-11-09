@props(['owner'])
{{-- Title and tabs at the top of every share link page. --}}
<div class="space-y-3">
    <h1 class="text-xl font-semibold">{{ $owner->name }}'s filament</h1>
    <nav class="flex gap-1 border-b border-stone-200 text-sm" aria-label="Share link">
        @foreach ([
            'share' => ['Filament', route('share', $owner->share_token)],
            'share.print-request' => ['Request a print', route('share.print-request', $owner->share_token)],
        ] as $routeName => [$label, $url])
            <a href="{{ $url }}" @if (request()->routeIs($routeName)) aria-current="page" @endif @class([
                '-mb-px border-b-2 px-3 py-2',
                'border-emerald-600 font-semibold text-emerald-700' => request()->routeIs($routeName),
                'border-transparent text-stone-600 hover:border-stone-300 hover:text-stone-900' => ! request()->routeIs($routeName),
            ])>{{ $label }}</a>
        @endforeach
    </nav>
</div>

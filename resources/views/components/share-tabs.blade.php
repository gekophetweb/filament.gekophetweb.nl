@props(['owner'])
{{-- Tabs at the top of the share link's pages. --}}
<nav class="flex gap-1 border-b border-stone-200 text-sm" aria-label="Share link">
    @foreach ([
        'share' => ['Request a print', route('share', $owner->share_token)],
        'share.suggestions' => ['Suggestions', route('share.suggestions', $owner->share_token)],
    ] as $routeName => [$label, $url])
        <a href="{{ $url }}" @if (request()->routeIs($routeName)) aria-current="page" @endif @class([
            '-mb-px border-b-2 px-3 py-2',
            'border-emerald-600 font-semibold text-emerald-700' => request()->routeIs($routeName),
            'border-transparent text-stone-600 hover:border-stone-300 hover:text-stone-900' => ! request()->routeIs($routeName),
        ])>{{ $label }}</a>
    @endforeach
</nav>

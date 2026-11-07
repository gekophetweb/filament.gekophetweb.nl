<!DOCTYPE html>
<html lang="en" @class(['h-full', 'dark' => \App\Support\Theme::current(request()) === \App\Support\Theme::DARK])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-stone-100 text-stone-900 antialiased">
    @auth
        <header class="border-b border-stone-200 bg-white">
            <nav class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3">
                <a href="{{ route('catalog') }}" class="font-semibold tracking-tight">Spool Tracker</a>
                <a href="{{ route('catalog') }}" @class(['text-sm', 'font-semibold text-emerald-700' => request()->routeIs('catalog', 'filaments.*')])>Catalog</a>
                <a href="{{ route('spools') }}" @class(['text-sm', 'font-semibold text-emerald-700' => request()->routeIs('spools*')])>My spools</a>
                <a href="{{ route('usage') }}" @class(['text-sm', 'font-semibold text-emerald-700' => request()->routeIs('usage')])>Usage</a>
                <a href="{{ route('share-export') }}" @class(['text-sm', 'font-semibold text-emerald-700' => request()->routeIs('share-export')])>Share &amp; export</a>
                <a href="{{ route('devices') }}" @class(['text-sm', 'font-semibold text-emerald-700' => request()->routeIs('devices')])>Devices</a>
                <a href="{{ route('docs.api') }}" @class(['text-sm', 'font-semibold text-emerald-700' => request()->routeIs('docs.*')])>API docs</a>
                <x-theme-toggle class="ml-auto" />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-stone-500 hover:text-stone-900">Sign out {{ auth()->user()->name }}</button>
                </form>
            </nav>
        </header>
    @else
        <div class="mx-auto flex max-w-7xl justify-end px-4 pt-3">
            <x-theme-toggle />
        </div>
    @endauth

    <main class="mx-auto max-w-7xl px-4 py-6">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>

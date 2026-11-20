<!DOCTYPE html>
<html lang="en" @class(['h-full', 'dark' => \App\Support\Theme::current(request()) === \App\Support\Theme::DARK])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="{{ \App\Support\Theme::current(request()) === \App\Support\Theme::DARK ? '#1c1917' : '#ffffff' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-stone-100 text-stone-900 antialiased">
    @auth
        @php($navLinks = [
            ['Catalog', route('catalog'), ['catalog', 'filaments.*'], 0],
            ['My spools', route('spools'), ['spools*'], 0],
            ['Usage', route('usage'), ['usage'], 0],
            ['Share & export', route('share-export'), ['share-export'], 0],
            ['Print requests', route('print-requests'), ['print-requests'], auth()->user()->printRequests()->unread()->count()],
            ['Suggestions', route('suggestions'), ['suggestions'], auth()->user()->suggestions()->unread()->count()],
            ['Devices', route('devices'), ['devices'], 0],
            ['API docs', route('docs.api'), ['docs.*'], 0],
        ])
        <header class="site-header border-b border-stone-200 bg-white">
            <div class="header-inner mx-auto flex max-w-7xl items-center gap-6 px-4 py-3">
                <a href="{{ route('catalog') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                    <img src="/images/logo.svg" alt="" class="size-7">
                    Spool Tracker
                </a>
                <nav class="site-nav ml-auto text-sm" aria-label="Primary">
                    <button type="button" class="menu-button rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-50"
                            popovertarget="site-menu" aria-controls="site-menu">
                        <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                            <path d="M2 4h12M2 8h12M2 12h12" />
                        </svg>
                        <span>Menu</span>
                    </button>
                    <ul id="site-menu" class="site-menu" popover="auto">
                        @foreach ($navLinks as [$label, $url, $patterns, $unread])
                            <li>
                                <a href="{{ $url }}" @if (request()->routeIs(...$patterns)) aria-current="page" @endif>
                                    {{ $label }}
                                    @if ($unread)
                                        <span class="ml-0.5 rounded-full bg-emerald-600 px-1.5 text-xs font-semibold text-white tabular-nums" aria-label="{{ $unread }} unread">{{ $unread }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                        <li class="site-menu-actions">
                            <x-theme-toggle />
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="text-stone-500 hover:text-stone-900">Sign out {{ auth()->user()->name }}</button>
                            </form>
                        </li>
                    </ul>
                </nav>
            </div>
        </header>
        <x-missing-settings />
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

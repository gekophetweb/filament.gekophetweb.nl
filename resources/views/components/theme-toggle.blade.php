@php($isDark = \App\Support\Theme::current(request()) === \App\Support\Theme::DARK)
<button type="button" {{ $attributes->merge(['class' => 'rounded-md px-2 py-1 text-sm text-stone-500 hover:bg-stone-100 hover:text-stone-900']) }}
        x-data="{ dark: @js($isDark) }"
        x-on:click="dark = ! dark;
            document.documentElement.classList.toggle('dark', dark);
            document.cookie = '{{ \App\Support\Theme::COOKIE }}=' + (dark ? 'dark' : 'light') + '; path=/; max-age=31536000; samesite=lax' + (location.protocol === 'https:' ? '; secure' : '')"
        x-bind:aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
        x-bind:title="dark ? 'Switch to light mode' : 'Switch to dark mode'"
        aria-label="{{ $isDark ? 'Switch to light mode' : 'Switch to dark mode' }}">
    <span x-text="dark ? '☀ Light' : '☾ Dark'">{{ $isDark ? '☀ Light' : '☾ Dark' }}</span>
</button>

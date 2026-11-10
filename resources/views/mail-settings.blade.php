@extends('layouts.app', ['title' => 'Mail settings'])

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">
        <h1 class="text-xl font-semibold">Mail settings</h1>

        @if (session('mail_settings_error'))
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ session('mail_settings_error') }}</p>
        @endif

        @unless ($unlocked)
            <section class="rounded-xl bg-white p-4 text-sm text-stone-600 shadow-sm">
                <p>Open this page with a one-time link. On the server, run:</p>
                <pre class="mt-2 overflow-x-auto rounded bg-stone-100 px-3 py-2 font-mono text-xs">docker compose exec app php artisan setup:mail-url</pre>
            </section>
        @else
            @if (session('mail_settings_saved'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">
                    Saved. The app was reloaded with the new mail settings.
                    @foreach (session('mail_settings_warnings', []) as $warning)
                        <p class="mt-1 text-amber-800">{{ $warning }}</p>
                    @endforeach
                </div>
            @endif
            @error('save') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

            <form method="POST" action="{{ route('mail-settings.update') }}" class="rounded-xl bg-white p-4 shadow-sm">
                @csrf
                {{-- Enter in a field saves, instead of pressing the first button in the form (Generate a new key). --}}
                <button type="submit" name="action" value="save" class="hidden" tabindex="-1" aria-hidden="true"></button>
                <p class="mb-4 text-sm text-stone-600">
                    Saved to <code class="font-mono">storage/app/.env.local</code>, like the setup wizard's other settings. Saving reloads the app.
                </p>
                @include('setup.mail-fields', [
                    'live' => false,
                    'values' => $values,
                    'dkimKey' => $dkimKey,
                    'passwordStored' => $passwordStored,
                    'keyStored' => $keyStored,
                    'testTo' => $testTo,
                ])
                <div class="mt-4 flex justify-end">
                    <button type="submit" name="action" value="save" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Save mail settings</button>
                </div>
            </form>
        @endunless
    </div>
@endsection

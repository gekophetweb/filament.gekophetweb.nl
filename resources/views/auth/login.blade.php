@extends('layouts.app', ['title' => 'Sign in'])

@section('content')
    <div class="mx-auto mt-24 max-w-sm rounded-xl bg-white p-8 text-center shadow-sm">
        <h1 class="text-xl font-semibold">Spool Tracker</h1>
        <p class="mt-2 text-sm text-stone-500">Sign in with your organization account.</p>

        @if (session('error'))
            <p class="mt-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</p>
        @endif

        <a href="{{ route('sso.redirect') }}"
           class="mt-6 inline-block w-full rounded-lg bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">
            Sign in with SSO
        </a>
    </div>
@endsection

{{--
    Who is using a share page: "signed in as", the sign-in options (email code or SSO), or why they cannot send anything.
    $page: which share page to come back to after signing in or out ('' for the print request page, 'suggestions').
--}}
@php($pendingEmail = \App\Http\Controllers\Auth\PrintRequesterEmailCodeController::pendingEmail())

@if (session('print_requester_error'))
    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
        {{ session('print_requester_error') }}
    </div>
@endif

@if ($requester)
    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-stone-600">
        <span>Signed in as <strong class="text-stone-900">{{ $requester->email }}</strong></span>
        @guest
            <form method="POST" action="{{ route('share.sign-out', $owner->share_token) }}">
                @csrf
                <input type="hidden" name="return" value="{{ $page }}">
                <button type="submit" class="underline hover:text-stone-900">Sign out</button>
            </form>
        @endguest
    </div>
@endif

@if (! $requester)
    <div class="mt-3 space-y-3 rounded-lg border border-stone-200 bg-stone-50 p-3 text-sm">
        <p>{{ $owner->name }} only takes requests and suggestions from people they know. Sign in, or get a code by email, so they can see who you are.</p>

        @if ($pendingEmail)
            <form method="POST" action="{{ route('share.email-code.verify', $owner->share_token) }}" class="space-y-2">
                @csrf
                <input type="hidden" name="return" value="{{ $page }}">
                <p>
                    If {{ $owner->name }} takes requests from <strong>{{ $pendingEmail }}</strong>,
                    a {{ \App\Http\Controllers\Auth\PrintRequesterEmailCodeController::CODE_LENGTH }}-digit code is on its way there.
                    It works for {{ \App\Http\Controllers\Auth\PrintRequesterEmailCodeController::CODE_MINUTES }} minutes.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <input name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="12"
                           aria-label="Code from the email" placeholder="123456"
                           class="w-32 rounded-md border-stone-300 px-2 py-1.5 font-mono tracking-widest">
                    <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Continue</button>
                </div>
                @error('code', 'emailCode') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
            </form>
            <form method="POST" action="{{ route('share.email-code.cancel', $owner->share_token) }}">
                @csrf
                <input type="hidden" name="return" value="{{ $page }}">
                <button type="submit" class="text-stone-600 underline hover:text-stone-900">Use another email</button>
            </form>
        @else
            <form method="POST" action="{{ route('share.email-code', $owner->share_token) }}" class="space-y-2">
                @csrf
                <input type="hidden" name="return" value="{{ $page }}">
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">Your name</span>
                        <input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"
                               class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
                        @error('name', 'emailCode') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-stone-500">Your email</span>
                        <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"
                               class="mt-1 w-full rounded-md border-stone-300 px-2 py-1.5">
                        @error('email', 'emailCode') <span class="text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                </div>
                <button type="submit" class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">Email me a code</button>
            </form>
            <p class="text-stone-600">
                Or <a href="{{ route('share.sign-in', array_filter([$owner->share_token, 'return' => $page])) }}" class="font-medium underline hover:text-stone-900">sign in with SSO</a>.
            </p>
        @endif
    </div>
@elseif (! $isAllowed)
    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm" role="alert">
        {{ $owner->name }} does not take print requests or suggestions from {{ $requester->email }}. Ask them to add you, or sign in with another account.
    </div>
@endif

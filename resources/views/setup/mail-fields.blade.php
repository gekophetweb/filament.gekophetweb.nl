{{--
    Mail settings fields, shared by the setup wizard's Mail step (Livewire, $live = true) and the mail settings page (a plain form).
    $values: field => value. $dkimKey: the private key in use or just generated (for the DNS record), or null.
    $passwordStored / $keyStored: a password or key exists, so leaving the field empty keeps it.
--}}
@php
    $bind = fn (string $field) => $live
        ? 'wire:model="'.$field.'"'
        : 'name="'.$field.'" value="'.e(old($field, $values[$field] ?? '')).'"';
    $error = fn (string $field) => $errors->first($field);
    $value = fn (string $field) => (string) ($live ? ($values[$field] ?? '') : old($field, $values[$field] ?? ''));
    $selector = \Illuminate\Support\Str::lower($value('mail_dkim_selector')) ?: \App\Actions\Setup\SaveMailSettings::DEFAULT_DKIM_SELECTOR;
    $domain = \Illuminate\Support\Str::lower($value('mail_dkim_domain')) ?: \Illuminate\Support\Str::after($value('mail_from_address'), '@');
    $record = $dkimKey && $domain ? \App\Support\Mail\DkimKey::dnsRecord($dkimKey, $selector, $domain) : null;
@endphp

<div x-data="{ mailer: @js(old('mail_mailer', $values['mail_mailer'])) }" class="space-y-6">
    <div>
        <h3 class="text-sm font-semibold">How the app sends email</h3>
        <div class="mt-2 grid gap-2 sm:grid-cols-3">
            @foreach (\App\Actions\Setup\SaveMailSettings::MAILERS as $key => $label)
                <label class="cursor-pointer rounded-lg border px-3 py-2 text-sm"
                       :class="mailer === @js($key) ? 'border-emerald-600 bg-emerald-50' : 'border-stone-300 hover:border-stone-400'">
                    <input type="radio" value="{{ $key }}" x-model="mailer" class="sr-only"
                           @if ($live) wire:model="mail_mailer" @else name="mail_mailer" @endif>
                    <span class="font-medium">{{ $label }}</span>
                    <span class="mt-0.5 block text-xs text-stone-500">
                        {{ match ($key) {
                            'smtp' => 'Print request emails and sign-in codes reach people.',
                            'resend' => 'Only an API key; Resend signs the mail for your domain.',
                            default => 'Nothing is delivered; for trying the app out.',
                        } }}
                    </span>
                </label>
            @endforeach
        </div>
        @if ($error('mail_mailer')) <p class="mt-1 text-sm text-red-700">{{ $error('mail_mailer') }}</p> @endif
    </div>

    <div x-show="mailer === 'smtp'" class="grid gap-3 sm:grid-cols-[1fr_7rem_9rem]">
        <label class="block text-sm">
            <span class="font-medium">SMTP server</span>
            <input {!! $bind('mail_host') !!} placeholder="smtp.example.com" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
            @if ($error('mail_host')) <span class="mt-1 block text-red-700">{{ $error('mail_host') }}</span> @endif
        </label>
        <label class="block text-sm">
            <span class="font-medium">Port</span>
            <input {!! $bind('mail_port') !!} inputmode="numeric" placeholder="587" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
            @if ($error('mail_port')) <span class="mt-1 block text-red-700">{{ $error('mail_port') }}</span> @endif
        </label>
        <label class="block text-sm">
            <span class="font-medium">Encryption</span>
            <select @if ($live) wire:model="mail_scheme" @else name="mail_scheme" @endif class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                @foreach (['' => 'Automatic', 'smtp' => 'STARTTLS (587)', 'smtps' => 'TLS (465)'] as $scheme => $label)
                    <option value="{{ $scheme }}" @selected(! $live && old('mail_scheme', $values['mail_scheme']) === $scheme)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm sm:col-span-1">
            <span class="font-medium">User name</span>
            <input {!! $bind('mail_username') !!} autocomplete="off" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
            @if ($error('mail_username')) <span class="mt-1 block text-red-700">{{ $error('mail_username') }}</span> @endif
        </label>
        <label class="block text-sm sm:col-span-2">
            <span class="font-medium">Password</span>
            <input type="password" autocomplete="new-password" placeholder="{{ $passwordStored ? 'Saved; leave empty to keep it' : '' }}"
                   @if ($live) wire:model="mail_password" @else name="mail_password" @endif x-bind:disabled="mailer !== 'smtp'"
                   class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
            @if ($error('mail_password')) <span class="mt-1 block text-red-700">{{ $error('mail_password') }}</span> @endif
        </label>
    </div>

    {{-- Resend's SMTP relay (see the "resend" mailer in config/mail.php): the API key is the password. --}}
    <div x-show="mailer === 'resend'" class="space-y-2">
        <label class="block text-sm">
            <span class="font-medium">Resend API key</span>
            <input type="password" autocomplete="new-password" placeholder="{{ $passwordStored ? 'Saved; leave empty to keep it' : 're_…' }}"
                   @if ($live) wire:model="mail_password" @else name="mail_password" @endif x-bind:disabled="mailer !== 'resend'"
                   class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
            @if ($error('mail_password')) <span class="mt-1 block text-red-700">{{ $error('mail_password') }}</span> @endif
        </label>
        <p class="text-xs text-stone-500">
            Create a key with "Sending access" at <a href="https://resend.com/api-keys" target="_blank" rel="noopener noreferrer" class="text-emerald-700 underline">resend.com/api-keys ↗</a>,
            and verify the from address's domain under Domains in Resend first; Resend only sends from verified domains and signs that mail with DKIM itself.
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-sm">
            <span class="font-medium">Send from address</span>
            <input type="email" {!! $bind('mail_from_address') !!} placeholder="spools@example.com" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
            @if ($error('mail_from_address')) <span class="mt-1 block text-red-700">{{ $error('mail_from_address') }}</span> @endif
        </label>
        <label class="block text-sm">
            <span class="font-medium">Send from name</span>
            <input {!! $bind('mail_from_name') !!} placeholder="Filament Spools" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            @if ($error('mail_from_name')) <span class="mt-1 block text-red-700">{{ $error('mail_from_name') }}</span> @endif
        </label>
    </div>

    <div x-show="mailer === 'smtp'">
        <h3 class="text-sm font-semibold">DKIM signing (optional)</h3>
        <p class="mt-1 text-xs text-stone-500">
            Signs every email so receivers can check it really comes from your domain, which keeps it out of spam folders.
            Leave the domain empty to send unsigned (for example when your mail provider signs for you).
        </p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">Domain</span>
                <input {!! $bind('mail_dkim_domain') !!} placeholder="example.com" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
                @if ($error('mail_dkim_domain')) <span class="mt-1 block text-red-700">{{ $error('mail_dkim_domain') }}</span> @endif
            </label>
            <label class="block text-sm">
                <span class="font-medium">Selector</span>
                <input {!! $bind('mail_dkim_selector') !!} placeholder="{{ \App\Actions\Setup\SaveMailSettings::DEFAULT_DKIM_SELECTOR }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
                @if ($error('mail_dkim_selector')) <span class="mt-1 block text-red-700">{{ $error('mail_dkim_selector') }}</span> @endif
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="font-medium">Private key</span>
                <textarea rows="4" placeholder="{{ $keyStored ? 'A key is set; leave empty to keep it, or paste or generate a new one' : '-----BEGIN PRIVATE KEY-----' }}"
                          @if ($live) wire:model="mail_dkim_private_key" @else name="mail_dkim_private_key" @endif
                          class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-xs"></textarea>
                @if ($error('mail_dkim_private_key')) <span class="mt-1 block text-red-700">{{ $error('mail_dkim_private_key') }}</span> @endif
            </label>
        </div>
        <button type="{{ $live ? 'button' : 'submit' }}" @if ($live) wire:click="generateDkimKey" @else name="action" value="generate" formnovalidate @endif
                class="mt-2 rounded-lg border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50">Generate a new key</button>

        @if ($record)
            <div class="mt-3 rounded-lg border border-stone-200 bg-stone-50 p-3 text-sm">
                <p class="font-medium">Publish this TXT record in your DNS:</p>
                <dl class="mt-2 grid gap-1 sm:grid-cols-[max-content_1fr] sm:gap-x-3">
                    <dt class="text-stone-500">Name</dt><dd class="font-mono break-all">{{ $record['name'] }}</dd>
                    <dt class="text-stone-500">Value</dt><dd class="font-mono text-xs break-all">{{ $record['value'] }}</dd>
                </dl>
                <p class="mt-2 text-xs text-stone-500">With another domain or selector above, the name changes to match. Receivers only trust the signature once this record is live.</p>
            </div>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-3 border-t border-stone-100 pt-4">
        @if ($live)
            <button type="button" wire:click="sendTestEmail" wire:loading.attr="disabled"
                    class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50 disabled:opacity-50">
                Send a test email to {{ $testTo }}
            </button>
        @else
            <input type="email" name="test_to" value="{{ old('test_to', $testTo) }}" aria-label="Send the test email to" placeholder="you@example.com"
                   class="w-64 max-w-full rounded-lg border border-stone-300 px-3 py-1.5 font-mono text-sm">
            <button type="submit" name="action" value="test" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm hover:bg-stone-50">Send a test email</button>
        @endif
        @if (session('mail_test_result'))
            <span class="text-sm text-emerald-700" role="status">{{ session('mail_test_result') }}</span>
        @endif
        @if ($error('mail_test'))
            <span class="text-sm text-red-700" role="alert">{{ $error('mail_test') }}</span>
        @endif
    </div>
</div>

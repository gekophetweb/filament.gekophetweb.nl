<div class="mx-auto mt-8 max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight">Set up Spool Tracker</h1>
    <p class="mt-1 text-sm text-stone-500">Connect single sign-on. When you save, everything you enter here is written to <code class="font-mono">storage/app/.env.local</code>, a settings file of this installation only, next to your own <code class="font-mono">.env</code>.</p>

    @if ($saved)
        {{-- Done --}}
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-emerald-700">✓ Setup complete</h2>
            <p class="mt-2 text-sm">The settings are saved and the app has been reloaded. This setup page is now closed for good.</p>

            @foreach ($warnings as $warning)
                <p wire:key="warning-{{ $loop->index }}" class="mt-3 rounded bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $warning }} Restart the app with <code class="font-mono">docker compose up -d --force-recreate app</code>.</p>
            @endforeach

            <ol class="mt-4 list-decimal space-y-1 pl-5 text-sm text-stone-700">
                <li>Open <a href="{{ $app_url }}/login" class="font-medium text-emerald-700 underline">{{ $app_url }}/login</a> and sign in.</li>
                <li>Remove the test redirect URI <code class="font-mono">{{ $testCallback }}</code> from your identity provider.</li>
                <li>The settings are in <code class="font-mono">storage/app/.env.local</code> in the app storage volume; values there win over <code class="font-mono">.env</code>. See the README to change them later.</li>
            </ol>
        </div>
    @elseif (! $unlocked)
        {{-- Token --}}
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Enter the setup token</h2>
            <p class="mt-2 text-sm text-stone-600">The setup link with its token is printed when the container starts. On the Docker host, run:</p>
            <pre class="mt-2 overflow-x-auto rounded bg-stone-900 px-3 py-2 font-mono text-sm text-stone-100">docker compose logs app | grep "Setup URL"</pre>
            <p class="mt-2 text-sm text-stone-600">Open that link, or paste only the token here.</p>

            @if (session('setup.token_error'))
                <p class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('setup.token_error') }}</p>
            @endif

            <form wire:submit="unlock" class="mt-4 flex gap-2">
                <input type="password" wire:model="token" autocomplete="off" placeholder="Setup token"
                       class="w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
                <button class="rounded-lg bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Continue</button>
            </form>
            @error('token') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    @else
        {{-- Stepper --}}
        <ol class="mt-6 flex flex-wrap gap-2 text-sm">
            @foreach (\App\Livewire\Setup::STEPS as $number => $label)
                <li wire:key="step-{{ $number }}" @class([
                    'rounded-full px-3 py-1',
                    'bg-emerald-700 text-white' => $number === $step,
                    'bg-emerald-100 text-emerald-800' => $number < $step,
                    'bg-stone-200 text-stone-500' => $number > $step,
                ])>{{ $number }}. {{ $label }}</li>
            @endforeach
        </ol>

        <div class="mt-4 rounded-xl bg-white p-6 shadow-sm">
            @if (session('setup.notice'))
                <p class="mb-4 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('setup.notice') }}</p>
            @endif

            @if ($step === 1)
                <h2 class="text-lg font-semibold">Where will people open the app?</h2>

                @unless ($envWritable)
                    <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        <p class="font-medium">The app cannot write <code class="font-mono">storage/app/.env.local</code>.</p>
                        <p class="mt-1">The storage volume must be writable for the container user (uid 33). Fix it, then reload this page:</p>
                        <pre class="mt-2 overflow-x-auto rounded bg-stone-900 px-3 py-2 font-mono text-stone-100">docker compose exec -u root app chown -R www-data: /app/storage</pre>
                    </div>
                @endunless

                <label class="mt-4 block text-sm font-medium" for="app_url">Public address</label>
                <input id="app_url" type="url" wire:model.live.debounce.400ms="app_url" placeholder="https://spools.example.com"
                       class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                @error('app_url') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-stone-500">The address of the Cloudflare Tunnel hostname, without a path.</p>

                @include('livewire.setup-redirect-uris')
            @elseif ($step === 2)
                @php($provider = $presets[$preset])
                <h2 class="text-lg font-semibold">Which sign-in provider do you use?</h2>
                <p class="mt-1 text-sm text-stone-600">Any provider that speaks OpenID Connect works. Pick yours for instructions; if it is not listed, choose "Other OpenID Connect".</p>

                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($presets as $key => $option)
                        <label wire:key="preset-{{ $key }}" @class([
                            'cursor-pointer rounded-lg border px-3 py-2 text-sm',
                            'border-emerald-600 bg-emerald-50' => $preset === $key,
                            'border-stone-300 hover:border-stone-400' => $preset !== $key,
                        ])>
                            <input type="radio" wire:model.live="preset" value="{{ $key }}" class="sr-only">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="font-medium">{{ $option['label'] }}</span>
                                <span class="shrink-0 text-[10px] tracking-wide text-stone-400 uppercase">{{ $option['kind'] === 'Any' ? 'Self-hosted / cloud' : $option['kind'] }}</span>
                            </span>
                            <span class="mt-0.5 block text-xs text-stone-500">{{ $option['summary'] }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4 rounded-lg bg-stone-50 p-4">
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="text-sm font-semibold">Create the client in {{ $provider['label'] }}</h3>
                        <a href="{{ $provider['docs'] }}" target="_blank" rel="noopener noreferrer" class="text-xs text-emerald-700 underline">Official guide ↗</a>
                    </div>

                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-stone-700">
                        @foreach ($provider['steps'] as $instruction)
                            <li wire:key="instruction-{{ $preset }}-{{ $loop->index }}" class="break-words">{{ \App\Support\Setup\SsoPresets::fill($instruction, $publicCallback, $testCallback) }}</li>
                        @endforeach
                    </ol>

                    @if ($provider['test_note'])
                        <p class="mt-2 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">For the test sign-in: {{ $provider['test_note'] }}</p>
                    @endif

                    @if ($provider['snippet'])
                        <div wire:key="snippet-{{ $preset }}" x-data="{ copied: false }" class="mt-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-stone-500">Example configuration</p>
                                <button type="button"
                                        x-on:click="navigator.clipboard.writeText($refs.snippet.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="rounded border border-stone-300 bg-white px-2 py-1 text-xs hover:bg-stone-50"
                                        x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                            </div>
                            <pre x-ref="snippet" class="mt-1 overflow-x-auto rounded bg-stone-900 px-3 py-2 font-mono text-xs text-stone-100">{{ \App\Support\Setup\SsoPresets::fill($provider['snippet'], $publicCallback, $testCallback) }}</pre>
                        </div>
                    @endif

                    <details class="mt-3 text-sm" @if ($preset === 'generic') open @endif>
                        <summary class="cursor-pointer text-stone-600">Settings the provider may ask for</summary>
                        <dl class="mt-2 grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1">
                            @foreach (\App\Support\Setup\SsoPresets::clientRequirements() as $setting => $value)
                                <dt wire:key="req-{{ $loop->index }}" class="text-stone-500">{{ $setting }}</dt>
                                <dd wire:key="req-value-{{ $loop->index }}">{{ $value }}</dd>
                            @endforeach
                        </dl>
                    </details>
                </div>

                @include('livewire.setup-redirect-uris')

                <h3 class="mt-6 text-sm font-semibold">Enter what {{ $provider['label'] }} gave you</h3>
                <p class="mt-1 text-xs text-stone-500">Paste the values here; you do not edit any file yourself. The secret is kept encrypted until you save, and then written only to <code class="font-mono">storage/app/.env.local</code>.</p>

                @if ($provider['tenant'])
                    <label class="mt-4 block text-sm font-medium" for="tenant">{{ $provider['tenant']['label'] }}</label>
                    <input id="tenant" wire:model.live.debounce.400ms="tenant" placeholder="{{ $provider['tenant']['placeholder'] }}"
                           class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
                    <p class="mt-1 text-xs text-stone-500">{{ $provider['tenant']['hint'] }}</p>
                @endif

                <label class="mt-4 block text-sm font-medium" for="issuer">Issuer URL</label>
                <input id="issuer" type="url" wire:model.live.debounce.400ms="issuer" placeholder="{{ $provider['issuer_placeholder'] }}"
                       @readonly($provider['issuer'] !== null)
                       class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm read-only:bg-stone-100">
                @error('issuer') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-stone-500">{{ $provider['issuer_hint'] }}</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium" for="client_id">Client ID</label>
                        <input id="client_id" wire:model="client_id" autocomplete="off"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
                        @error('client_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-stone-500">{{ $provider['client_id_hint'] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="client_secret">Client secret</label>
                        <input id="client_secret" type="password" wire:model="client_secret" autocomplete="new-password"
                               placeholder="{{ $secretStored ? '•••••••• (set, type to replace)' : '' }}"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
                        @error('client_secret') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-stone-500">{{ $provider['client_secret_hint'] }}</p>
                    </div>
                </div>

                <button type="button" wire:click="checkConnection" wire:loading.attr="disabled"
                        class="mt-4 rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-50">
                    <span wire:loading.remove wire:target="checkConnection">Check connection</span>
                    <span wire:loading wire:target="checkConnection">Checking…</span>
                </button>

                @if ($checks !== [])
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($checks as $check)
                            <li wire:key="check-{{ $loop->index }}" class="flex gap-2">
                                <span @class([
                                    'w-4 shrink-0 font-bold',
                                    'text-emerald-700' => $check['status'] === 'ok',
                                    'text-amber-600' => $check['status'] === 'warn',
                                    'text-red-700' => $check['status'] === 'fail',
                                ])>{{ ['ok' => '✓', 'warn' => '!', 'fail' => '✗'][$check['status']] }}</span>
                                <span><span class="font-medium">{{ $check['label'] }}</span> <span class="text-stone-500">{{ $check['detail'] }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @elseif ($step === 3)
                <h2 class="text-lg font-semibold">Who may use the app?</h2>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ([
                        'everyone' => ['Anyone (open sign-up)', 'Everyone with an account at the provider can create an account here, from any network. Each person only sees their own spools.'],
                        'listed' => ['Only people I list', 'Only the accounts and networks you enter below. For a private installation.'],
                    ] as $key => [$title, $description])
                        <label wire:key="audience-{{ $key }}" @class([
                            'cursor-pointer rounded-lg border px-3 py-2 text-sm',
                            'border-emerald-600 bg-emerald-50' => $audience === $key,
                            'border-stone-300 hover:border-stone-400' => $audience !== $key,
                        ])>
                            <input type="radio" wire:model.live="audience" value="{{ $key }}" class="sr-only">
                            <span class="font-medium">{{ $title }}</span>
                            <span class="mt-0.5 block text-xs text-stone-500">{{ $description }}</span>
                        </label>
                    @endforeach
                </div>

                @if ($audience === 'everyone')
                    <p class="mt-4 text-sm text-stone-600">The website, the Android app API and the MCP server accept every IP address; sign-in, tokens and rate limits still protect them. The first sign-in creates the account.</p>
                    @if ($preset === 'google')
                        <p class="mt-2 rounded bg-amber-50 px-3 py-2 text-sm text-amber-900">Google only lets the test users on the OAuth consent screen sign in while the app is in "Testing". Publish the app there (Audience → Publish app) before you open it to everyone.</p>
                    @endif
                    @error('allowed_emails') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                @else
                <label class="mt-4 block text-sm font-medium" for="allowed_emails">Allowed accounts</label>
                <textarea id="allowed_emails" wire:model="allowed_emails" rows="3" placeholder="me@example.com, @family.example"
                          class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm"></textarea>
                @error('allowed_emails') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-stone-500">Full email addresses and/or whole domains starting with @, separated by commas or new lines. Anyone else is refused, even with a valid account at the provider.</p>

                <h3 class="mt-6 text-sm font-semibold">From which networks?</h3>
                <p class="mt-1 text-xs text-stone-500">Visitors through the Cloudflare Tunnel are seen by their public IP (your home's public IP, not a <code class="font-mono">192.168.x.x</code> address). Comma separated; <code class="font-mono">*</code> means any address.</p>

                @foreach (['ips_web' => 'Website (required)', 'ips_api' => 'Android app API (empty = blocked)', 'ips_mcp' => 'MCP server (empty = blocked)'] as $field => $label)
                    <div wire:key="{{ $field }}">
                        <label class="mt-3 block text-sm font-medium" for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" wire:model="{{ $field }}" placeholder="203.0.113.7, 192.168.1.0/24"
                               class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-mono text-sm">
                        @error($field) <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                @endif
            @elseif ($step === 4)
                <h2 class="text-lg font-semibold">Test the sign-in</h2>
                <p class="mt-2 text-sm text-stone-600">Sign in once with these settings before anything is saved, so a wrong value cannot lock you out. The provider sends you back to <code class="font-mono">{{ $testCallback }}</code>.</p>

                @if (session('setup.test_error'))
                    <p class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('setup.test_error') }}</p>
                @endif

                @if ($tested)
                    <p class="mt-4 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800">✓ Signed in as {{ $tested['name'] }} ({{ $tested['email'] }}).</p>
                @endif

                <a href="{{ route('setup.test') }}"
                   class="mt-4 inline-block rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">
                    {{ $tested ? 'Test again' : 'Sign in with '.$presets[$preset]['label'] }}
                </a>
                @error('test') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            @else
                <h2 class="text-lg font-semibold">Review and save</h2>

                <dl class="mt-4 grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-stone-500">Public address</dt><dd class="font-mono break-all">{{ $app_url }}</dd>
                    <dt class="text-stone-500">Callback</dt><dd class="font-mono break-all">{{ $publicCallback }}</dd>
                    <dt class="text-stone-500">Provider</dt><dd>{{ $presets[$preset]['label'] }}</dd>
                    <dt class="text-stone-500">Issuer</dt><dd class="font-mono break-all">{{ $issuer }}</dd>
                    <dt class="text-stone-500">Client ID</dt><dd class="font-mono break-all">{{ $client_id }}</dd>
                    <dt class="text-stone-500">Client secret</dt><dd class="font-mono">••••••••</dd>
                    <dt class="text-stone-500">Allowed accounts</dt><dd class="font-mono break-all">{{ $allowed_emails === '*' ? 'Anyone (open sign-up)' : $allowed_emails }}</dd>
                    <dt class="text-stone-500">Website IPs</dt><dd class="font-mono break-all">{{ $ips_web === '*' ? 'Any address' : $ips_web }}</dd>
                    <dt class="text-stone-500">API IPs</dt><dd class="font-mono break-all">{{ $ips_api === '*' ? 'Any address' : ($ips_api ?: 'blocked') }}</dd>
                    <dt class="text-stone-500">MCP IPs</dt><dd class="font-mono break-all">{{ $ips_mcp === '*' ? 'Any address' : ($ips_mcp ?: 'blocked') }}</dd>
                    <dt class="text-stone-500">Tested as</dt><dd>{{ $tested['email'] ?? 'not tested' }}</dd>
                </dl>

                @unless ($envWritable)
                    <p class="mt-4 rounded bg-amber-50 px-3 py-2 text-sm text-amber-800">The app cannot write <code class="font-mono">storage/app/.env.local</code>. See step 1.</p>
                @endunless
                @error('test') <p class="mt-3 text-sm text-red-700">{{ $message }}</p> @enderror
                @error('save') <p class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror
                @foreach (\App\Actions\Setup\SaveSetup::FIELDS as $field)
                    @error($field) <p wire:key="save-error-{{ $field }}" class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                @endforeach
            @endif

            <div class="mt-6 flex items-center justify-between border-t border-stone-100 pt-4">
                @if ($step > 1)
                    <button type="button" wire:click="back" class="text-sm text-stone-600 hover:text-stone-900">← Back</button>
                @else
                    <span></span>
                @endif

                @if ($step < 5)
                    <button type="button" wire:click="next" wire:loading.attr="disabled"
                            @disabled($step === 2 && ! $connectionChecked || $step === 4 && ! $tested)
                            class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                        Next →
                    </button>
                @else
                    <button type="button" wire:click="save" wire:loading.attr="disabled"
                            class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-50">
                        <span wire:loading.remove wire:target="save">Save settings</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>

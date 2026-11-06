<div class="mt-4 rounded-lg border border-stone-200 p-4">
    <p class="text-sm font-medium">Add both redirect URIs to your identity provider</p>

    @foreach (['For normal sign-in' => $publicCallback, 'Only for the test in step 4 (remove afterwards)' => $testCallback] as $purpose => $uri)
        <div wire:key="uri-{{ $loop->index }}" x-data="{ copied: false }" class="mt-2">
            <p class="text-xs text-stone-500">{{ $purpose }}</p>
            <div class="mt-1 flex items-center gap-2">
                <code x-ref="uri" class="flex-1 overflow-x-auto rounded bg-stone-100 px-2 py-1 font-mono text-sm">{{ $uri }}</code>
                <button type="button"
                        x-on:click="navigator.clipboard.writeText($refs.uri.textContent.trim()); copied = true; setTimeout(() => copied = false, 1500)"
                        class="rounded border border-stone-300 px-2 py-1 text-xs hover:bg-stone-50"
                        x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
        </div>
    @endforeach
</div>

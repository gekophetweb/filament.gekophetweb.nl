<?php

namespace App\Support\Setup;

use Illuminate\Support\Facades\Process;

/**
 * Makes settings just written to storage/app/.env.local take effect in the running app.
 */
class AppReloader
{
    /**
     * Rebuild the config in fresh processes (this one still holds the old
     * values) and restart Octane workers and queue workers.
     *
     * @param  list<string>  $keys  Written keys, removed from the child environment so the new file wins.
     * @return list<string> Warnings about reload steps that did not succeed.
     */
    public function reload(array $keys): array
    {
        $warnings = [];
        $artisan = fn (string $command) => Process::path(base_path())
            ->timeout(60)
            ->env(array_fill_keys($keys, false))
            ->run(['php', 'artisan', $command]);

        $config = $artisan(app()->configurationIsCached() ? 'config:cache' : 'config:clear');
        if ($config->failed()) {
            $warnings[] = 'Rebuilding the config cache failed: '.trim($config->errorOutput() ?: $config->output());
        }

        $artisan('queue:restart');

        if ($artisan('octane:status')->successful()) {
            // After the response, so this request is not cut off by its own worker restart.
            defer(fn () => $artisan('octane:reload'));
        }

        return $warnings;
    }
}

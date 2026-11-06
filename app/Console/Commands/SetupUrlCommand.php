<?php

namespace App\Console\Commands;

use App\Support\Setup\SetupState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('setup:url')]
#[Description('Print the one-time setup wizard link while the app is not set up yet (run by the container entrypoint)')]
class SetupUrlCommand extends Command
{
    public function handle(SetupState $setup): int
    {
        if ($setup->isComplete()) {
            return self::SUCCESS;
        }

        $url = rtrim((string) config('setup.local_url'), '/').'/setup?token='.$setup->token();

        $this->line('');
        $this->line('  This app is not set up yet. Open the setup wizard on the Docker host:');
        $this->line("  Setup URL: {$url}");
        $this->line('  On a remote server, first run: ssh -L 8000:127.0.0.1:8000 <user>@<server>');
        $this->line('  Without a browser: docker compose exec app php artisan sso:setup');
        $this->line('');

        return self::SUCCESS;
    }
}

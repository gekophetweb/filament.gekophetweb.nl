<?php

namespace App\Console\Commands;

use App\Support\Setup\MailSettingsAccess;
use App\Support\Setup\SetupState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('setup:mail-url')]
#[Description('Print a one-time link to the mail settings page (from address, SMTP and DKIM) of an app that is set up')]
class MailSettingsUrlCommand extends Command
{
    public function handle(SetupState $setup, MailSettingsAccess $access): int
    {
        if (! $setup->isComplete()) {
            $this->warn('This app is not set up yet. The setup wizard has a Mail step; run `php artisan setup:url` for its link.');

            return self::FAILURE;
        }

        $url = rtrim((string) config('setup.local_url'), '/').'/mail-settings?token='.$access->issueToken();

        $this->line('');
        $this->line('  Open the mail settings on the Docker host (works once, for '.config('setup.mail_token_minutes').' minutes):');
        $this->line("  Mail settings URL: {$url}");
        $this->line('  On a remote server, first run: ssh -L 8000:127.0.0.1:8000 <user>@<server>');
        $this->line('');

        return self::SUCCESS;
    }
}

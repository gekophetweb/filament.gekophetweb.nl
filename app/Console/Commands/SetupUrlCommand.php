<?php

namespace App\Console\Commands;

use App\Support\Setup\MailSettingsAccess;
use App\Support\Setup\MissingSettings;
use App\Support\Setup\SetupState;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('setup:url')]
#[Description('Print the one-time setup wizard link while the app is not set up yet, or what a set-up app still lacks (run by the container entrypoint)')]
class SetupUrlCommand extends Command
{
    public function handle(SetupState $setup, MissingSettings $settings, MailSettingsAccess $mailAccess): int
    {
        if ($setup->isComplete()) {
            $this->reportMissingSettings($settings, $mailAccess);

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

    /**
     * List missing settings (and tips) with a one-time link to the page that fixes them.
     * Prints nothing when everything is set.
     */
    private function reportMissingSettings(MissingSettings $settings, MailSettingsAccess $mailAccess): void
    {
        $missing = $settings->missing();
        $tips = $settings->tips();

        if ($missing === [] && $tips === []) {
            return;
        }

        $this->line('');
        $this->line($missing !== [] ? '  This app is set up, but some settings are missing:' : '  This app is set up. A tip:');
        foreach ($missing as $item) {
            $this->line("  - {$item['message']}");
        }
        foreach ($tips as $item) {
            $this->line("  - Tip: {$item['message']}");
        }

        // A link only for something missing, so a tip alone does not put a fresh token in the log at every start.
        if ($missing !== []) {
            $token = $mailAccess->issueToken();
            $this->line('  Fix them with one of these links (works once, for '.config('setup.mail_token_minutes').' minutes):');
            $this->line('  Mail settings URL: '.MailSettingsUrlCommand::url((string) config('app.url'), $token));
            $this->line('  On the Docker host: '.MailSettingsUrlCommand::url((string) config('setup.local_url'), $token));
        }
        $this->line('  '.($missing !== [] ? 'A new link' : 'To change it').': docker compose exec app php artisan setup:mail-url');
        $this->line('');
    }
}

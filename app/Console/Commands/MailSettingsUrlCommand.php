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

        $token = $access->issueToken();

        $this->line('');
        $this->line('  Open the mail settings with one of these links (works once, for '.config('setup.mail_token_minutes').' minutes):');
        $this->line('  Mail settings URL: '.self::url((string) config('app.url'), $token));
        $this->line('  On the Docker host: '.self::url((string) config('setup.local_url'), $token));
        $this->line('  The public link only opens from an address on the web allow-list (ALLOWED_IPS_WEB).');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * The mail settings link with a one-time token, on the given base URL.
     */
    public static function url(string $baseUrl, string $token): string
    {
        return rtrim($baseUrl, '/').'/mail-settings?token='.$token;
    }
}

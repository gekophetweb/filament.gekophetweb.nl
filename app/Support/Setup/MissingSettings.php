<?php

namespace App\Support\Setup;

use Illuminate\Support\Str;

/**
 * Settings an installation that is already set up still lacks, such as ones added in a later version
 * than the one it was set up with. Reported in the container log at start (`php artisan setup:url`)
 * and in a banner for signed-in users. Each entry says what is wrong and which command fixes it.
 */
class MissingSettings
{
    /** Domains reserved for examples (RFC 2606); mail from or to them never arrives. */
    private const EXAMPLE_DOMAINS = ['example.com', 'example.net', 'example.org'];

    private const EXAMPLE_TLDS = ['example', 'test', 'invalid', 'localhost'];

    /**
     * Settings that keep part of the app from working.
     *
     * @return list<array{key: string, message: string, command: string}>
     */
    public function missing(): array
    {
        $missing = [];

        if (config('mail.default') === 'log') {
            $missing[] = $this->mail('mail_log', 'Email only goes to the log, so print request emails and sign-in codes are not delivered.');
        }

        $from = (string) config('mail.from.address');
        if ($from === '' || $this->isExampleAddress($from)) {
            $missing[] = $this->mail('mail_from', $from === ''
                ? 'There is no send-from address for email.'
                : "The send-from address is still the example address {$from}.");
        }

        return $missing;
    }

    /**
     * Optional settings worth adding; they are reported, but never as something wrong.
     *
     * @return list<array{key: string, message: string, command: string}>
     */
    public function tips(): array
    {
        $tips = [];

        if (config('mail.default') === 'smtp' && blank(config('mail.mailers.smtp.dkim.domain'))) {
            $tips[] = $this->mail('mail_dkim', 'Email is sent without a DKIM signature. Add one unless your mail provider signs for you, so it stays out of spam folders.');
        }

        return $tips;
    }

    /**
     * @return array{key: string, message: string, command: string}
     */
    private function mail(string $key, string $message): array
    {
        return ['key' => $key, 'message' => $message, 'command' => 'php artisan setup:mail-url'];
    }

    private function isExampleAddress(string $address): bool
    {
        $domain = Str::lower(Str::afterLast($address, '@'));

        foreach (self::EXAMPLE_DOMAINS as $example) {
            if ($domain === $example || str_ends_with($domain, '.'.$example)) {
                return true;
            }
        }

        return in_array(Str::afterLast($domain, '.'), self::EXAMPLE_TLDS, true);
    }
}

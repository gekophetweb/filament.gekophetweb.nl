<?php

namespace App\Actions\Setup;

use App\Support\EnvWriter;
use App\Support\Mail\DkimKey;
use App\Support\Setup\AppReloader;
use Closure;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The mail settings from the setup wizard's Mail step and the mail settings page: how mail is sent,
 * the from address, and DKIM signing. Written to storage/app/.env.local like the other wizard settings.
 */
class SaveMailSettings
{
    public const FIELDS = [
        'mail_mailer', 'mail_host', 'mail_port', 'mail_scheme', 'mail_username', 'mail_password',
        'mail_from_address', 'mail_from_name', 'mail_dkim_domain', 'mail_dkim_selector', 'mail_dkim_private_key',
    ];

    /**
     * Ways to send: real mail through an SMTP server or Resend, or only writing it to the log.
     * For Resend the mail_password field holds the API key (RESEND_API_KEY); see the "resend" mailer in config/mail.php.
     */
    public const MAILERS = [
        'smtp' => 'Send through an SMTP server',
        'resend' => 'Send through Resend',
        'log' => 'Only write emails to the log',
    ];

    public const DEFAULT_DKIM_SELECTOR = 'spools';

    public function __construct(private AppReloader $reloader) {}

    /**
     * The settings the app runs with now, for the form. The password and DKIM key are left out.
     *
     * @return array<string, string>
     */
    public static function current(): array
    {
        $smtp = config('mail.mailers.smtp');
        $mailer = config('mail.default');

        return [
            'mail_mailer' => array_key_exists($mailer, self::MAILERS) ? $mailer : 'log',
            'mail_host' => (string) ($smtp['host'] ?? ''),
            'mail_port' => (string) ($smtp['port'] ?? '587'),
            'mail_scheme' => (string) ($smtp['scheme'] ?? ''),
            'mail_username' => (string) ($smtp['username'] ?? ''),
            'mail_password' => '',
            'mail_from_address' => (string) config('mail.from.address'),
            'mail_from_name' => (string) config('mail.from.name'),
            'mail_dkim_domain' => (string) ($smtp['dkim']['domain'] ?? ''),
            'mail_dkim_selector' => (string) ($smtp['dkim']['selector'] ?? ''),
            'mail_dkim_private_key' => '',
        ];
    }

    /**
     * The SMTP password or Resend API key the app runs with now for this way of sending, if any.
     */
    public static function currentPassword(string $mailer): ?string
    {
        $password = match ($mailer) {
            'smtp' => config('mail.mailers.smtp.password'),
            'resend' => config('mail.mailers.resend.password'),
            default => null,
        };

        return filled($password) ? (string) $password : null;
    }

    /**
     * The DKIM private key the app runs with now, if any.
     */
    public static function currentDkimKey(): ?string
    {
        return DkimKey::decode(config('mail.mailers.smtp.dkim.private_key'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string> Trimmed values; fields the chosen way of sending does not use are empty.
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $input = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $input);
        $input['mail_dkim_domain'] = Str::lower((string) ($input['mail_dkim_domain'] ?? ''));
        $input['mail_dkim_selector'] = Str::lower((string) ($input['mail_dkim_selector'] ?? ''));

        $validated = Validator::make($input, $this->rules($input), [
            'mail_host.required_if' => 'Enter the SMTP server.',
            'mail_port.required_if' => 'Enter the SMTP port.',
            'mail_password.required_if' => 'Enter your Resend API key.',
            'mail_password.starts_with' => 'A Resend API key starts with re_.',
            'mail_dkim_selector.required_with' => 'Enter a selector to sign with DKIM.',
            'mail_dkim_private_key.required_with' => 'Generate or paste a private key to sign with DKIM.',
        ], [
            'mail_host' => 'SMTP server',
            'mail_port' => 'port',
            'mail_username' => 'user name',
            'mail_password' => 'password',
            'mail_from_address' => 'from address',
            'mail_from_name' => 'from name',
            'mail_dkim_domain' => 'DKIM domain',
            'mail_dkim_selector' => 'selector',
            'mail_dkim_private_key' => 'private key',
        ])->validate();

        $values = array_map(fn ($value) => (string) $value, array_merge(array_fill_keys(self::FIELDS, ''), $validated));
        $values['mail_from_address'] = Str::lower($values['mail_from_address']);

        if ($values['mail_mailer'] !== 'smtp') {
            foreach (['mail_host', 'mail_port', 'mail_scheme', 'mail_username'] as $field) {
                $values[$field] = '';
            }
        }

        if ($values['mail_mailer'] === 'log') {
            $values['mail_password'] = '';
        }

        // Resend signs with DKIM itself.
        if ($values['mail_mailer'] === 'resend') {
            $values['mail_dkim_domain'] = '';
        }

        if ($values['mail_dkim_domain'] === '') {
            $values['mail_dkim_selector'] = $values['mail_dkim_private_key'] = '';
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values  Output of validate().
     * @return array<string, string>
     */
    public function envValues(array $values): array
    {
        return [
            'MAIL_MAILER' => $values['mail_mailer'],
            'MAIL_HOST' => $values['mail_host'],
            'MAIL_PORT' => $values['mail_port'],
            'MAIL_SCHEME' => $values['mail_scheme'],
            'MAIL_USERNAME' => $values['mail_username'],
            'MAIL_PASSWORD' => $values['mail_mailer'] === 'smtp' ? $values['mail_password'] : '',
            'RESEND_API_KEY' => $values['mail_mailer'] === 'resend' ? $values['mail_password'] : '',
            'MAIL_FROM_ADDRESS' => $values['mail_from_address'],
            'MAIL_FROM_NAME' => $values['mail_from_name'],
            'MAIL_DKIM_DOMAIN' => $values['mail_dkim_domain'],
            'MAIL_DKIM_SELECTOR' => $values['mail_dkim_selector'],
            'MAIL_DKIM_PRIVATE_KEY' => $values['mail_dkim_private_key'] === '' ? '' : DkimKey::encode($values['mail_dkim_private_key']),
        ];
    }

    /**
     * Write the settings and reload the app; for installs that are already set up.
     *
     * @param  array<string, mixed>  $input
     * @return list<string> Warnings about reload steps that did not succeed.
     */
    public function handle(array $input): array
    {
        $env = $this->envValues($this->validate($input));
        $writer = new EnvWriter(config('setup.env_path'), config('setup.backup_path'));

        if (! $writer->isWritable()) {
            throw new RuntimeException('The app cannot write '.config('setup.env_path').'.');
        }

        $writer->set($env);

        return $this->reloader->reload(array_keys($env));
    }

    /**
     * Send a test email right away (not queued) with these settings, before they are saved.
     *
     * @param  array<string, string>  $values  Output of validate().
     *
     * @throws \Throwable When the mail server refuses it.
     */
    public function sendTest(array $values, string $to): void
    {
        $mailer = 'mail-settings-test';

        config(["mail.mailers.{$mailer}" => match ($values['mail_mailer']) {
            'smtp' => array_merge(config('mail.mailers.smtp'), [
                'transport' => 'smtp',
                'url' => null,
                'scheme' => $values['mail_scheme'] ?: null,
                'host' => $values['mail_host'],
                'port' => (int) $values['mail_port'],
                'username' => $values['mail_username'] ?: null,
                'password' => $values['mail_password'] ?: null,
                'timeout' => 15,
                'dkim' => [
                    'domain' => $values['mail_dkim_domain'],
                    'selector' => $values['mail_dkim_selector'],
                    'private_key' => $values['mail_dkim_private_key'],
                ],
            ]),
            'resend' => array_merge(config('mail.mailers.resend'), ['password' => $values['mail_password'], 'timeout' => 15]),
            default => ['transport' => 'log', 'channel' => config('mail.mailers.log.channel')],
        }]);
        Mail::purge($mailer);

        Mail::mailer($mailer)->raw(
            "This is a test email from {$values['mail_from_name']}.\n\n"
            .($values['mail_dkim_domain'] !== ''
                ? "It is signed with DKIM for {$values['mail_dkim_domain']} (selector {$values['mail_dkim_selector']}). Check the DKIM-Signature result in the message headers."
                : 'DKIM signing is off.'),
            fn ($message) => $message->to($to)
                ->from($values['mail_from_address'], $values['mail_from_name'])
                ->subject('Test email from '.$values['mail_from_name']),
        );

        Mail::purge($mailer);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, mixed>>
     */
    private function rules(array $input): array
    {
        return [
            'mail_mailer' => ['required', Rule::in(array_keys(self::MAILERS))],
            'mail_host' => ['nullable', 'required_if:mail_mailer,smtp', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'mail_port' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'between:1,65535'],
            'mail_scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => array_merge(
                ['nullable', 'required_if:mail_mailer,resend', 'string', 'max:1000'],
                ($input['mail_mailer'] ?? '') === 'resend' ? ['starts_with:re_'] : [],
            ),
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:100'],
            'mail_dkim_domain' => ['nullable', 'string', 'max:253', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $this->coversFromAddress((string) ($input['mail_from_address'] ?? ''))],
            'mail_dkim_selector' => ['nullable', 'required_with:mail_dkim_domain', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9_-]*[a-z0-9])?)*$/'],
            'mail_dkim_private_key' => ['nullable', 'required_with:mail_dkim_domain', 'string', 'max:10000', function (string $attribute, mixed $value, Closure $fail): void {
                if (! DkimKey::isValid((string) $value)) {
                    $fail('This is not an RSA private key in PEM format (-----BEGIN PRIVATE KEY-----).');
                }
            }],
        ];
    }

    /**
     * DMARC only passes when the DKIM domain is the from address's domain or one of its parents.
     */
    private function coversFromAddress(string $fromAddress): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($fromAddress): void {
            $fromDomain = Str::lower(Str::after($fromAddress, '@'));
            $domain = (string) $value;

            if ($domain !== '' && $fromDomain !== $domain && ! str_ends_with($fromDomain, '.'.$domain)) {
                $fail("Use the from address's domain ({$fromDomain}) or a parent of it, otherwise receivers do not trust the signature.");
            }
        };
    }
}

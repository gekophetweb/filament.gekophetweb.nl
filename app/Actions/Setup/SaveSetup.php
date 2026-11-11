<?php

namespace App\Actions\Setup;

use App\Support\EnvWriter;
use App\Support\Setup\AppReloader;
use App\Support\Setup\SetupState;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Validates the first-run settings, writes them to storage/app/.env.local, closes the wizard
 * and reloads the running app so the new values take effect.
 */
class SaveSetup
{
    public const FIELDS = ['app_url', 'issuer', 'client_id', 'client_secret', 'allowed_emails', 'ips_web', 'ips_api', 'ips_mcp'];

    private const LIST_FIELDS = ['allowed_emails', 'ips_web', 'ips_api', 'ips_mcp'];

    /** List value that allows everyone: any verified email, or any IP address. */
    public const EVERYONE = '*';

    public function __construct(private SetupState $setup, private AppReloader $reloader) {}

    public function writer(): EnvWriter
    {
        return new EnvWriter(config('setup.env_path'), config('setup.backup_path'));
    }

    /**
     * Validate some or all fields and return them normalized (trimmed, lists
     * comma-joined and de-duplicated, emails lower-cased).
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>|null  $only
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?array $only = null): array
    {
        $fields = $only ?? self::FIELDS;
        $input = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $input);

        $validated = Validator::make(
            $input,
            array_intersect_key($this->rules(), array_flip($fields)),
            [
                'app_url.url' => 'Enter the full public address, for example https://spools.example.com.',
                'issuer.url' => 'Enter the issuer URL, starting with https://.',
                'client_id.regex' => 'The client ID may not contain spaces.',
                'allowed_emails.required' => 'Add at least one email address or @domain, otherwise nobody can sign in.',
                'ips_web.required' => 'Add at least one IP range; an empty list blocks every browser in production.',
            ],
            [
                'app_url' => 'public address',
                'client_id' => 'client ID',
                'client_secret' => 'client secret',
                'allowed_emails' => 'allowed accounts',
                'ips_web' => 'web IP ranges',
                'ips_api' => 'API IP ranges',
                'ips_mcp' => 'MCP IP ranges',
            ],
        )->validate();

        $normalized = [];

        foreach ($fields as $field) {
            $value = (string) ($validated[$field] ?? '');

            $normalized[$field] = match (true) {
                in_array($field, self::LIST_FIELDS, true) => self::joinList($field === 'allowed_emails' ? strtolower($value) : $value),
                in_array($field, ['app_url', 'issuer'], true) => rtrim($value, '/'),
                default => $value,
            };
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $values  Output of validate().
     * @return array<string, string>
     */
    public function envValues(array $values): array
    {
        return [
            'APP_URL' => $values['app_url'],
            'SSO_PROVIDER' => 'oidc',
            'SSO_ISSUER' => $values['issuer'],
            'SSO_CLIENT_ID' => $values['client_id'],
            'SSO_CLIENT_SECRET' => $values['client_secret'],
            'SSO_REDIRECT_URI' => $values['app_url'].'/auth/callback',
            'SSO_ALLOWED_EMAILS' => $values['allowed_emails'],
            'ALLOWED_IPS_WEB' => $values['ips_web'],
            'ALLOWED_IPS_API' => $values['ips_api'],
            'ALLOWED_IPS_MCP' => $values['ips_mcp'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $extraEnv  Further .env values to write with these, such as the mail settings.
     * @return list<string> Warnings about reload steps that did not succeed.
     */
    public function handle(array $input, array $extraEnv = []): array
    {
        $env = $this->envValues($this->validate($input)) + $extraEnv;
        $writer = $this->writer();

        if (! $writer->isWritable()) {
            throw new RuntimeException('The app cannot write '.config('setup.env_path').'.');
        }

        $writer->set($env);
        $this->setup->complete();

        return $this->reloader->reload(array_keys($env));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'app_url' => ['required', 'url:http,https', 'max:255', $this->withoutPath()],
            'issuer' => ['required', 'url:http,https', 'max:500'],
            'client_id' => ['required', 'string', 'max:255', 'regex:/^\S+$/'],
            'client_secret' => ['required', 'string', 'max:2000'],
            'allowed_emails' => ['required', 'string', $this->eachEntry(
                fn (string $entry) => match (true) {
                    $entry === self::EVERYONE => true,
                    str_starts_with($entry, '@') => preg_match('/^@([a-z0-9-]+\.)+[a-z]{2,}$/i', $entry) === 1,
                    default => filter_var($entry, FILTER_VALIDATE_EMAIL) !== false,
                },
                '":entry" is not an email address or @domain.',
            )],
            'ips_web' => ['required', 'string', $this->eachEntry($this->isIpOrCidr(...), '":entry" is not an IP address or CIDR range.')],
            'ips_api' => ['nullable', 'string', $this->eachEntry($this->isIpOrCidr(...), '":entry" is not an IP address or CIDR range.')],
            'ips_mcp' => ['nullable', 'string', $this->eachEntry($this->isIpOrCidr(...), '":entry" is not an IP address or CIDR range.')],
        ];
    }

    private function withoutPath(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $parts = parse_url((string) $value);

            if (trim($parts['path'] ?? '', '/') !== '' || isset($parts['query']) || isset($parts['fragment'])) {
                $fail('Use only the scheme and host, without a path or query.');
            }
        };
    }

    /**
     * @param  Closure(string): bool  $isValid
     */
    private function eachEntry(Closure $isValid, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($isValid, $message): void {
            foreach (self::splitList((string) $value) as $entry) {
                if (! $isValid($entry)) {
                    $fail(str_replace(':entry', $entry, $message));

                    return;
                }
            }
        };
    }

    private function isIpOrCidr(string $entry): bool
    {
        if ($entry === self::EVERYONE) {
            return true;
        }

        [$address, $bits] = array_pad(explode('/', $entry, 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($bits === null) {
            return true;
        }

        $max = str_contains($address, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits <= $max;
    }

    /**
     * Comma-joined, de-duplicated entries; just "*" when the list allows everyone.
     */
    private static function joinList(string $value): string
    {
        $entries = self::splitList($value);

        return in_array(self::EVERYONE, $entries, true) ? self::EVERYONE : implode(',', $entries);
    }

    /**
     * @return list<string>
     */
    public static function splitList(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $value) ?: []))));
    }
}

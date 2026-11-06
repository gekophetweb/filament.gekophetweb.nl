<?php

namespace App\Console\Commands;

use App\Actions\Setup\SaveSetup;
use App\Support\Setup\OidcDiscoveryCheck;
use App\Support\Setup\SetupState;
use App\Support\Setup\SsoPresets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;
use function Laravel\Prompts\warning;

#[Signature('sso:setup')]
#[Description('Terminal version of the first-run setup wizard: configure SSO and write it to storage/app/.env.local')]
class SsoSetupCommand extends Command
{
    public function handle(SaveSetup $save, SetupState $setup, OidcDiscoveryCheck $discovery): int
    {
        if ($setup->isComplete()) {
            error('Setup is already complete. The settings are in storage/app/.env.local; see the README to change them.');

            return self::FAILURE;
        }

        if (! $save->writer()->isWritable()) {
            error('The app cannot write '.config('setup.env_path').'. Run: docker compose exec -u root app chown -R www-data: /app/storage');

            return self::FAILURE;
        }

        $input = [];
        $input['app_url'] = $this->promptFor($save, 'app_url', text(...), [
            'label' => 'Public address of the app',
            'placeholder' => 'https://spools.example.com',
            'default' => rtrim((string) config('app.url'), '/'),
        ]);

        $preset = select(
            'Sign-in provider',
            array_map(fn (array $preset) => "{$preset['label']} — {$preset['summary']}", SsoPresets::all()),
            hint: 'Any OpenID Connect provider works; pick "Other" if yours is not listed.',
        );
        $provider = SsoPresets::all()[$preset];
        $callback = rtrim($input['app_url'], '/').'/auth/callback';

        note("Create the client in {$provider['label']}:\n".implode("\n", array_map(
            fn (string $step, int $index) => ($index + 1).'. '.SsoPresets::fill($step, $callback),
            $provider['steps'],
            array_keys($provider['steps']),
        )));

        if ($provider['snippet']) {
            $this->line(SsoPresets::fill($provider['snippet'], $callback));
        }

        table(['Setting', 'Value'], [
            ['Redirect URI', $callback],
            ...collect(SsoPresets::clientRequirements())->map(fn (string $value, string $setting) => [$setting, $value])->values(),
        ]);
        info("Guide: {$provider['docs']}");

        do {
            $input['issuer'] = $provider['tenant']
                ? SsoPresets::issuerFor($preset, text($provider['tenant']['label'], placeholder: $provider['tenant']['placeholder'], required: true, hint: $provider['tenant']['hint']))
                : (SsoPresets::issuerFor($preset) ?? $this->promptFor($save, 'issuer', text(...), [
                    'label' => 'Issuer URL',
                    'placeholder' => $provider['issuer_placeholder'],
                    'hint' => $provider['issuer_hint'],
                ]));

            $checks = spin(fn () => $discovery->run(rtrim($input['issuer'], '/')), 'Checking the issuer…');
            table(['', 'Check', 'Details'], array_map(
                fn (array $check) => [['ok' => '✓', 'warn' => '!', 'fail' => '✗'][$check['status']], $check['label'], $check['detail']],
                $checks,
            ));
        } while (! OidcDiscoveryCheck::passed($checks) && confirm('The issuer check failed. Enter it again?'));

        if (! OidcDiscoveryCheck::passed($checks)) {
            return self::FAILURE;
        }

        $input['client_id'] = $this->promptFor($save, 'client_id', text(...), ['label' => 'Client ID', 'hint' => $provider['client_id_hint']]);
        $input['client_secret'] = $this->promptFor($save, 'client_secret', password(...), ['label' => 'Client secret', 'hint' => $provider['client_secret_hint']]);
        $input['allowed_emails'] = $this->promptFor($save, 'allowed_emails', textarea(...), [
            'label' => 'Allowed accounts',
            'placeholder' => 'me@example.com, @family.example',
            'hint' => 'Emails and/or @domains, separated by commas or new lines.',
        ]);
        $input['ips_web'] = $this->promptFor($save, 'ips_web', text(...), [
            'label' => 'IP ranges for the website',
            'placeholder' => '203.0.113.7, 192.168.1.0/24',
            'hint' => 'Visitors through the Cloudflare Tunnel are seen by their public IP.',
        ]);
        $input['ips_api'] = $this->promptFor($save, 'ips_api', text(...), ['label' => 'IP ranges for the Android app API', 'hint' => 'Empty = blocked']);
        $input['ips_mcp'] = $this->promptFor($save, 'ips_mcp', text(...), ['label' => 'IP ranges for the MCP server', 'hint' => 'Empty = blocked']);

        $values = $save->validate($input);
        table(['Setting', 'Value'], collect($save->envValues($values))
            ->map(fn (string $value, string $key) => [$key, $key === 'SSO_CLIENT_SECRET' ? '••••••••' : $value])
            ->values()
            ->all());

        warning('The sign-in itself is not tested here. Use the web wizard for a test sign-in, or keep a shell open to fix storage/app/.env.local if it fails.');

        if (! confirm('Write these settings to storage/app/.env.local?')) {
            return self::FAILURE;
        }

        try {
            $warnings = $save->handle($input);
        } catch (RuntimeException $e) {
            error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($warnings as $message) {
            warning($message);
        }

        info('Saved. Sign in at '.$values['app_url'].'/login');
        note('If this ran in a one-off container (docker compose run), restart the app: docker compose up -d --force-recreate');

        return self::SUCCESS;
    }

    /**
     * Prompt for one field, validating it with the same rules as the web wizard.
     *
     * @param  callable(mixed ...): string  $prompt
     * @param  array<string, mixed>  $options
     */
    private function promptFor(SaveSetup $save, string $field, callable $prompt, array $options): string
    {
        return trim((string) $prompt(...$options, validate: function (string $value) use ($save, $field): ?string {
            try {
                $save->validate([$field => $value], [$field]);
            } catch (ValidationException $e) {
                return $e->validator->errors()->first($field);
            }

            return null;
        }));
    }
}

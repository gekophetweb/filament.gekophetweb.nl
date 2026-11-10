<?php

use App\Actions\Setup\SaveMailSettings;
use App\Support\Mail\DkimKey;
use App\Support\Mail\DkimSigningTransport;
use App\Support\Setup\MailSettingsAccess;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/mail-settings-test-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->dir);
    file_put_contents($this->dir.'/.env.local', "SSO_CLIENT_ID='client-1'\n");

    // An install that is set up (SSO configured), with its settings files in a temp dir.
    config([
        'services.sso.client_id' => 'client-1',
        'setup.env_path' => $this->dir.'/.env.local',
        'setup.backup_path' => $this->dir.'/backups',
        'setup.mail_token_path' => $this->dir.'/mail-settings.token',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

/** A DKIM key generated once for the whole file: generating RSA keys is slow. */
function dkimTestKey(): string
{
    static $key;

    return $key ??= DkimKey::generate();
}

/** @return array<string, string> */
function mailInput(array $overrides = []): array
{
    return array_merge([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_port' => '587',
        'mail_scheme' => '',
        'mail_username' => 'spools',
        'mail_password' => 'p4ss$word',
        'mail_from_address' => 'spools@mail.example.com',
        'mail_from_name' => 'Filament Spools',
        'mail_dkim_domain' => 'example.com',
        'mail_dkim_selector' => 'spools',
        'mail_dkim_private_key' => dkimTestKey(),
    ], $overrides);
}

function unlockMailSettings(): void
{
    $token = app(MailSettingsAccess::class)->issueToken();
    test()->get(route('mail-settings', ['token' => $token]))->assertRedirect(route('mail-settings'));
}

describe('DKIM signing', function () {
    it('signs every message with the configured domain and selector', function () {
        $transport = new DkimSigningTransport(new NullTransport, new DkimSigner(dkimTestKey(), 'example.com', 'spools'));
        $email = (new Email)->from('spools@example.com')->to('sam@example.org')->subject('Hi')->text('Hello');

        $sent = $transport->send($email);

        $signature = $sent->getOriginalMessage()->getHeaders()->get('DKIM-Signature')?->getBodyAsString();
        expect($signature)->toContain('d=example.com')->toContain('s=spools')
            ->and($sent->getEnvelope()->getRecipients()[0]->getAddress())->toBe('sam@example.org');
    });

    it('wraps the SMTP transport only when DKIM is set up', function () {
        config(['mail.mailers.smtp.dkim' => ['domain' => 'example.com', 'selector' => 'spools', 'private_key' => DkimKey::encode(dkimTestKey())]]);
        expect(Mail::mailer('smtp')->getSymfonyTransport())->toBeInstanceOf(DkimSigningTransport::class);

        Mail::purge('smtp');
        config(['mail.mailers.smtp.dkim' => ['domain' => null, 'selector' => null, 'private_key' => null]]);
        expect(Mail::mailer('smtp')->getSymfonyTransport())->toBeInstanceOf(EsmtpTransport::class);
    });

    it('gives the DNS record for the public key', function () {
        $record = DkimKey::dnsRecord(dkimTestKey(), 'spools', 'example.com');

        expect($record['name'])->toBe('spools._domainkey.example.com')
            ->and($record['value'])->toStartWith('v=DKIM1; k=rsa; p=MII')->not->toContain('-----');
    });
});

describe('settings', function () {
    it('turns the settings into .env values, with the key on one line', function () {
        $action = app(SaveMailSettings::class);
        $env = $action->envValues($action->validate(mailInput(['mail_from_address' => 'Spools@Mail.Example.com'])));

        expect($env)->toMatchArray([
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => 'smtp.example.com',
            'MAIL_FROM_ADDRESS' => 'spools@mail.example.com',
            'MAIL_DKIM_DOMAIN' => 'example.com',
            'MAIL_DKIM_SELECTOR' => 'spools',
        ])->and($env['MAIL_DKIM_PRIVATE_KEY'])->not->toContain("\n")
            ->and(DkimKey::decode($env['MAIL_DKIM_PRIVATE_KEY']))->toBe(trim(dkimTestKey())."\n");
    });

    it('drops the SMTP settings when mail only goes to the log', function () {
        $values = app(SaveMailSettings::class)->validate(mailInput(['mail_mailer' => 'log', 'mail_host' => '']));

        expect($values)->mail_host->toBe('')->mail_password->toBe('')->mail_from_address->toBe('spools@mail.example.com');
    });

    it('rejects settings that would not work', function (array $overrides, string $field) {
        expect(fn () => app(SaveMailSettings::class)->validate(mailInput($overrides)))
            ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($field));
    })->with([
        'no SMTP server' => [['mail_host' => ''], 'mail_host'],
        'bad port' => [['mail_port' => '70000'], 'mail_port'],
        'bad from address' => [['mail_from_address' => 'not-an-email'], 'mail_from_address'],
        'DKIM for another domain' => [['mail_dkim_domain' => 'other.example'], 'mail_dkim_domain'],
        'DKIM domain that only ends the same' => [['mail_dkim_domain' => 'ample.com'], 'mail_dkim_domain'],
        'no selector' => [['mail_dkim_selector' => ''], 'mail_dkim_selector'],
        'not a key' => [['mail_dkim_private_key' => 'hello'], 'mail_dkim_private_key'],
    ]);
});

describe('mail settings page', function () {
    it('opens with the one-time link from the command, only once', function () {
        $this->artisan('setup:mail-url')->expectsOutputToContain('Mail settings URL: http://127.0.0.1:8000/mail-settings?token=')->assertSuccessful();
        $token = json_decode(file_get_contents($this->dir.'/mail-settings.token'), true)['token'];

        $this->get(route('mail-settings'))->assertOk()->assertSee('setup:mail-url')->assertDontSee('Send from address');
        $this->get(route('mail-settings', ['token' => $token]))->assertRedirect(route('mail-settings'));
        $this->get(route('mail-settings'))->assertOk()->assertSee(['Send from address', 'DKIM signing']);

        $this->flushSession();
        $this->get(route('mail-settings', ['token' => $token]))->assertSessionHas('mail_settings_error');
        $this->get(route('mail-settings'))->assertDontSee('Send from address');
    });

    it('does not open with an expired link', function () {
        $token = app(MailSettingsAccess::class)->issueToken();
        $this->travel(config('setup.mail_token_minutes') + 1)->minutes();

        $this->get(route('mail-settings', ['token' => $token]))->assertSessionHas('mail_settings_error');
        $this->get(route('mail-settings'))->assertDontSee('Send from address');
    });

    it('is not there for requests through a proxy or tunnel', function () {
        $this->withHeader('CF-Connecting-IP', '203.0.113.7')->get(route('mail-settings'))->assertNotFound();
    });

    it('opens locally even when the web allow-list does not cover the Docker host', function () {
        config(['access.ips.web' => ['203.0.113.0/24']]);

        $this->get(route('mail-settings'))->assertOk();
        $this->get(route('login'))->assertForbidden();
    });

    it('refuses changes without the link', function () {
        Process::fake();

        $this->post(route('mail-settings.update'), mailInput() + ['action' => 'save'])->assertForbidden();
        Process::assertNothingRan();
    });

    it('saves the settings and reloads the app', function () {
        Process::fake();
        unlockMailSettings();

        $this->post(route('mail-settings.update'), mailInput() + ['action' => 'save'])
            ->assertRedirect(route('mail-settings'))->assertSessionHas('mail_settings_saved');

        expect(file_get_contents($this->dir.'/.env.local'))
            ->toContain("SSO_CLIENT_ID='client-1'")
            ->toContain("MAIL_FROM_ADDRESS='spools@mail.example.com'")
            ->toContain("MAIL_PASSWORD='p4ss\$word'")
            ->toContain("MAIL_DKIM_DOMAIN='example.com'")
            ->toContain("MAIL_DKIM_PRIVATE_KEY='".DkimKey::encode(dkimTestKey())."'");
        Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'artisan queue:restart'));
    });

    it('keeps the password and key in use when their fields are left empty', function () {
        Process::fake();
        config(['mail.mailers.smtp.password' => 'old-pass', 'mail.mailers.smtp.dkim.private_key' => DkimKey::encode(dkimTestKey())]);
        unlockMailSettings();

        $this->post(route('mail-settings.update'), mailInput(['mail_password' => '', 'mail_dkim_private_key' => '']) + ['action' => 'save'])
            ->assertSessionHasNoErrors();

        expect(file_get_contents($this->dir.'/.env.local'))
            ->toContain("MAIL_PASSWORD='old-pass'")
            ->toContain("MAIL_DKIM_PRIVATE_KEY='".DkimKey::encode(dkimTestKey())."'");
    });

    it('generates a key and shows the DNS record to publish, keeping it out of the form', function () {
        unlockMailSettings();

        $this->post(route('mail-settings.update'), mailInput(['mail_dkim_private_key' => '', 'mail_dkim_selector' => '', 'mail_dkim_domain' => '']) + ['action' => 'generate'])
            ->assertRedirect(route('mail-settings'));

        $this->get(route('mail-settings'))->assertOk()
            ->assertSee(['spools._domainkey.mail.example.com', 'v=DKIM1; k=rsa; p='])
            ->assertDontSee('BEGIN PRIVATE KEY');
    });

    it('sends a test email with the settings in the form', function () {
        Event::fake([MessageSent::class]);
        unlockMailSettings();

        $this->post(route('mail-settings.update'), mailInput(['mail_mailer' => 'log']) + ['action' => 'test', 'test_to' => 'me@example.com'])
            ->assertSessionHas('mail_test_result', 'Written to the log (addressed to me@example.com).');

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->getTo()[0]->getAddress() === 'me@example.com'
            && $event->message->getFrom()[0]->getAddress() === 'spools@mail.example.com');
    });
});

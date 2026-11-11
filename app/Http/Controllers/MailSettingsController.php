<?php

namespace App\Http\Controllers;

use App\Actions\Setup\SaveMailSettings;
use App\Support\Mail\DkimKey;
use App\Support\Setup\MailSettingsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The setup wizard's Mail step for an app that is already set up, opened with the one-time link from
 * `php artisan setup:mail-url`: on the Docker host, or through the tunnel from an address on the web allow-list.
 */
class MailSettingsController extends Controller
{
    /** Session key for a DKIM key generated here but not saved yet. */
    private const GENERATED_KEY = 'mail_settings.dkim_key';

    /** Secrets are never flashed back into the form. */
    private const SECRETS = ['mail_password', 'mail_dkim_private_key'];

    public function __construct(private MailSettingsAccess $access) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->filled('token')) {
            if (! $this->access->redeem((string) $request->query('token'))) {
                session()->flash('mail_settings_error', 'That link is not valid any more. Run `php artisan setup:mail-url` for a new one.');
            }

            // Drop the token from the address bar and browser history.
            return redirect()->route('mail-settings');
        }

        $generatedKey = $this->generatedKey();
        $currentKey = SaveMailSettings::currentDkimKey();

        return response()->view('mail-settings', [
            'unlocked' => $this->access->isUnlocked(),
            'values' => SaveMailSettings::current(),
            'dkimKey' => $generatedKey ?? $currentKey,
            'keyStored' => $generatedKey !== null || $currentKey !== null,
            'passwordStored' => filled(config('mail.mailers.smtp.password')),
            'testTo' => (string) config('mail.from.address'),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function update(Request $request, SaveMailSettings $mail): RedirectResponse
    {
        abort_unless($this->access->isUnlocked(), 403);

        $input = $this->input($request);
        $back = redirect()->route('mail-settings');

        if ($request->input('action') === 'generate') {
            session([self::GENERATED_KEY => Crypt::encryptString(DkimKey::generate())]);

            return $back->withInput(array_merge($request->except(self::SECRETS), [
                'mail_dkim_selector' => $input['mail_dkim_selector'] ?: SaveMailSettings::DEFAULT_DKIM_SELECTOR,
                'mail_dkim_domain' => $input['mail_dkim_domain'] ?: Str::after((string) $input['mail_from_address'], '@'),
            ]));
        }

        try {
            $values = $mail->validate($input);
        } catch (ValidationException $e) {
            return $back->withErrors($e->errors())->withInput($request->except(self::SECRETS));
        }

        if ($request->input('action') === 'test') {
            $to = (string) $request->input('test_to');

            if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
                return $back->withErrors(['mail_test' => 'Enter the address to send the test email to.'])->withInput($request->except(self::SECRETS));
            }

            try {
                $mail->sendTest($values, $to);
            } catch (Throwable $e) {
                return $back->withErrors(['mail_test' => 'Sending failed: '.$e->getMessage()])->withInput($request->except(self::SECRETS));
            }

            return $back->withInput($request->except(self::SECRETS))
                ->with('mail_test_result', $values['mail_mailer'] === 'log' ? "Written to the log (addressed to {$to})." : "Sent to {$to}.");
        }

        try {
            $warnings = $mail->handle($input);
        } catch (RuntimeException $e) {
            return $back->withErrors(['save' => $e->getMessage()])->withInput($request->except(self::SECRETS));
        }

        session()->forget(self::GENERATED_KEY);

        return $back->with('mail_settings_saved', true)->with('mail_settings_warnings', $warnings);
    }

    /**
     * The form, with an empty password or key meaning "keep the one in use" (or the key generated here).
     *
     * @return array<string, string>
     */
    private function input(Request $request): array
    {
        $input = array_map(fn ($value) => is_string($value) ? $value : '', $request->only(SaveMailSettings::FIELDS));
        $input += array_fill_keys(SaveMailSettings::FIELDS, '');

        if (trim($input['mail_password']) === '') {
            $input['mail_password'] = (string) config('mail.mailers.smtp.password');
        }

        if (trim($input['mail_dkim_private_key']) === '') {
            $input['mail_dkim_private_key'] = (string) ($this->generatedKey() ?? SaveMailSettings::currentDkimKey());
        }

        return $input;
    }

    private function generatedKey(): ?string
    {
        $key = session(self::GENERATED_KEY);

        return $key ? Crypt::decryptString($key) : null;
    }
}

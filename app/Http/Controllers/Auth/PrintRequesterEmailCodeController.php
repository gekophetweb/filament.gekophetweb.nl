<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PrintRequesterCode;
use App\Support\PrintRequester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Signing in to send print requests without SSO: the visitor gives a name and email and types in
 * the code that was mailed to them. Codes only go to emails the owner allows, but the page says the
 * same either way, so the owner's list cannot be probed.
 */
class PrintRequesterEmailCodeController extends Controller
{
    /** Session key holding the code waiting to be typed in. */
    public const SESSION_KEY = 'print_requester_code';

    public const CODE_LENGTH = 6;

    public const CODE_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public function send(Request $request, string $token): RedirectResponse
    {
        $owner = $this->ownerTakingRequests($token);

        $data = $request->validateWithBag('emailCode', [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);
        $email = Str::lower($data['email']);
        $code = str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
        $isAllowed = $owner->allowsPrintRequestsFrom($email);

        $request->session()->put(self::SESSION_KEY, [
            'email' => $email,
            'name' => $data['name'],
            // No code exists for an email the owner does not allow, so nothing typed in can match.
            'hash' => $isAllowed ? self::hash($code) : null,
            'expires_at' => now()->addMinutes(self::CODE_MINUTES)->getTimestamp(),
            'attempts' => 0,
        ]);

        if ($isAllowed) {
            Notification::route('mail', [$email => $data['name']])->notify(new PrintRequesterCode($code, $owner->name));
        }

        return redirect()->route('share', $token);
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $this->ownerTakingRequests($token);
        $pending = $request->session()->get(self::SESSION_KEY);
        $back = redirect()->route('share', $token);

        if (! is_array($pending) || $pending['expires_at'] < now()->getTimestamp() || $pending['attempts'] >= self::MAX_ATTEMPTS) {
            $request->session()->forget(self::SESSION_KEY);

            return $back->with('print_requester_error', 'That code has expired. Ask for a new one.');
        }

        $code = preg_replace('/\D/', '', (string) $request->input('code'));

        if ($pending['hash'] === null || ! hash_equals($pending['hash'], self::hash($code))) {
            $request->session()->put(self::SESSION_KEY.'.attempts', $pending['attempts'] + 1);

            return $back->withErrors(['code' => 'That code is not right. Check the email and try again.'], 'emailCode');
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();
        (new PrintRequester($pending['email'], $pending['name']))->remember();

        return $back;
    }

    /** Drop the code waiting to be typed in, to start over with another email. */
    public function cancel(Request $request, string $token): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('share', $token);
    }

    /**
     * The email waiting for a code to be typed in, if any.
     */
    public static function pendingEmail(): ?string
    {
        $pending = session(self::SESSION_KEY);

        return is_array($pending) && $pending['expires_at'] >= now()->getTimestamp() ? $pending['email'] : null;
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function ownerTakingRequests(string $token): User
    {
        $owner = User::where('share_token', $token)->firstOrFail();
        abort_unless($owner->takesPrintRequests(), 404);

        return $owner;
    }
}

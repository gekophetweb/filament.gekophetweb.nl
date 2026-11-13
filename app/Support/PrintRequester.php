<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Someone who signed in through SSO to send print requests through a share link.
 * Only the verified email and name are kept, in the session; they get no account in the app.
 * A signed-in user of the app counts as a requester with their own email.
 */
final readonly class PrintRequester
{
    /** Session key holding the signed-in requester. */
    public const SESSION_KEY = 'print_requester';

    /** Session key holding the share token to return to after the SSO round trip. */
    public const PENDING_SESSION_KEY = 'print_requester_pending_token';

    /** Session key holding which share page to return to after the SSO round trip. */
    public const RETURN_SESSION_KEY = 'print_requester_return';

    /** Share pages a sign-in can return to, by the "return" value the forms send; anything else is the print request page. */
    private const PAGES = ['suggestions' => 'share.suggestions'];

    public function __construct(public string $email, public string $name) {}

    /**
     * The route name of the share page to go back to after signing in or out.
     */
    public static function pageRoute(mixed $page): string
    {
        return is_string($page) ? (self::PAGES[$page] ?? 'share') : 'share';
    }

    public static function current(): ?self
    {
        $user = Auth::user();
        if ($user instanceof User && $user->email) {
            return new self($user->email, $user->name);
        }

        $stored = Session::get(self::SESSION_KEY);
        if (! is_array($stored) || ! is_string($stored['email'] ?? null) || $stored['email'] === '') {
            return null;
        }

        return new self($stored['email'], (string) ($stored['name'] ?? ''));
    }

    public function remember(): void
    {
        Session::put(self::SESSION_KEY, ['email' => $this->email, 'name' => $this->name]);
    }

    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}

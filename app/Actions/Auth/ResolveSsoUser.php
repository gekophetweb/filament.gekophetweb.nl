<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class ResolveSsoUser
{
    /**
     * Find or create the local user for an SSO identity.
     * Returns null when the identity is not on the allow-list.
     */
    public function handle(string $provider, SocialiteUser $identity): ?User
    {
        $email = Str::lower((string) $identity->getEmail());
        $raw = method_exists($identity, 'getRaw') ? $identity->getRaw() : [];

        if ($email === '' || ($raw['email_verified'] ?? true) === false || ! $this->isAllowed($email)) {
            return null;
        }

        $user = User::where('sso_provider', $provider)
            ->where('sso_subject', (string) $identity->getId())
            ->first();

        if ($user === null) {
            // An existing account with this email but a different subject is
            // not silently linked: that would allow account takeover.
            if (User::where('email', $email)->exists()) {
                return null;
            }

            $user = new User;
            $user->sso_provider = $provider;
            $user->sso_subject = (string) $identity->getId();
        }

        $user->email = $email;
        $user->name = $identity->getName() ?: Str::before($email, '@');
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    public function isAllowed(string $email): bool
    {
        return self::matchesAllowList($email, config('access.allowed_emails', []));
    }

    /**
     * @param  list<string>  $entries  Lower-case emails and/or "@domain" entries.
     */
    public static function matchesAllowList(string $email, array $entries): bool
    {
        $email = Str::lower($email);

        foreach ($entries as $entry) {
            if ($entry === $email || (str_starts_with($entry, '@') && str_ends_with($email, $entry))) {
                return true;
            }
        }

        return false;
    }
}

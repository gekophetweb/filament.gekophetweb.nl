<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Actions\Auth\ResolveSsoUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'sso_provider', 'sso_subject'])]
#[Hidden(['password', 'remember_token', 'share_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Same as the column default, so a user created in this request has it too. */
    protected $attributes = [
        'emails_print_requests' => true,
    ];

    /** @return HasMany<Spool, $this> */
    public function spools(): HasMany
    {
        return $this->hasMany(Spool::class);
    }

    /** @return HasMany<PrintRequest, $this> */
    public function printRequests(): HasMany
    {
        return $this->hasMany(PrintRequest::class);
    }

    /** @return HasMany<AccessRequest, $this> */
    public function accessRequests(): HasMany
    {
        return $this->hasMany(AccessRequest::class);
    }

    /**
     * Add an email to the print requester list, unless an entry already covers it.
     */
    public function allowPrintRequester(string $email): void
    {
        $entries = $this->printRequesterAllowList();

        if (! ResolveSsoUser::matchesAllowList($email, $entries)) {
            $entries[] = Str::lower($email);
            $this->forceFill(['print_requester_emails' => implode("\n", $entries)])->save();
        }
    }

    /** @return HasMany<Suggestion, $this> */
    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    /**
     * Whether another API token may be created (config access.max_tokens_per_user counts unexpired ones).
     */
    public function hasRoomForToken(): bool
    {
        $active = $this->tokens()->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();

        return $active < (int) config('access.max_tokens_per_user');
    }

    /**
     * Turn on the print request share link, or replace it so the old link stops working.
     */
    public function rotateShareToken(): string
    {
        $this->forceFill(['share_token' => Str::random(40)])->save();

        return $this->share_token;
    }

    public function stopSharing(): void
    {
        $this->forceFill(['share_token' => null])->save();
    }

    public function shareUrl(): ?string
    {
        return $this->share_token ? route('share', $this->share_token) : null;
    }

    /**
     * Who may send print requests through the share link: lower-case emails, "@domain" entries or "*".
     *
     * @return list<string>
     */
    public function printRequesterAllowList(): array
    {
        return self::splitAllowList((string) $this->print_requester_emails);
    }

    /**
     * Whether the share link takes print requests at all (someone is on the requester list).
     */
    public function takesPrintRequests(): bool
    {
        return $this->printRequesterAllowList() !== [];
    }

    /**
     * Whether this signed-in, verified email may send print requests. The owner always may, once requests are on.
     */
    public function allowsPrintRequestsFrom(string $email): bool
    {
        if (! $this->takesPrintRequests()) {
            return false;
        }

        return Str::lower($email) === Str::lower((string) $this->email)
            || ResolveSsoUser::matchesAllowList($email, $this->printRequesterAllowList());
    }

    /**
     * Split a list typed one per line (or comma separated) into lower-case, unique entries.
     *
     * @return list<string>
     */
    public static function splitAllowList(string $entries): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $entry) => Str::lower(trim($entry)),
            preg_split('/[\s,;]+/', $entries) ?: [],
        ))));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'emails_print_requests' => 'boolean',
        ];
    }
}

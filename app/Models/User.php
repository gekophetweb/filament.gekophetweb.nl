<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

    /**
     * Whether another API token may be created (config access.max_tokens_per_user counts unexpired ones).
     */
    public function hasRoomForToken(): bool
    {
        $active = $this->tokens()->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();

        return $active < (int) config('access.max_tokens_per_user');
    }

    /**
     * Turn on the read-only share link, or replace it so the old link stops working.
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

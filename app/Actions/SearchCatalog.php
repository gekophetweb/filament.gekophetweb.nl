<?php

namespace App\Actions;

use App\Enums\SpoolStatus;
use App\Models\FilamentProduct;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SearchCatalog
{
    public const MAX_PER_PAGE = 100;

    /** Values of the "owned" filter. */
    public const OWNED = '1';

    public const NOT_OWNED = '0';

    public const BOXED = 'boxed';

    public const ON_SPOOL = 'on_spool';

    public const RUNNING_OUT = 'running_out';

    public const OWNED_FILTERS = [self::OWNED, self::NOT_OWNED, self::BOXED, self::ON_SPOOL, self::RUNNING_OUT];

    /**
     * The "owned" filter is one of OWNED_FILTERS; anything else shows every product.
     *
     * @param  array{q?: ?string, material?: ?string, line?: ?string, color?: ?string, brand?: ?string, owned?: ?string, updated_since?: ?string}  $filters
     * @return Builder<FilamentProduct>
     */
    public function query(User $user, array $filters = []): Builder
    {
        $query = FilamentProduct::query()
            ->with(['brand', 'material'])
            ->withOwnedCount($user)
            ->where('is_active', true);

        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $query->where(fn (Builder $w) => $w
                ->whereRaw('lower(article_name) like ?', [$like])
                ->orWhereRaw('lower(article_number) like ?', [$like]));
        }

        if ($material = $filters['material'] ?? null) {
            $query->whereHas('material', fn (Builder $m) => $m->where('name', $material));
        }

        if ($brand = $filters['brand'] ?? null) {
            $query->whereHas('brand', fn (Builder $b) => $b->where('name', $brand));
        }

        if ($line = $filters['line'] ?? null) {
            $query->where('line', $line);
        }

        if ($color = $filters['color'] ?? null) {
            $query->where('color_family', $color);
        }

        if ($since = $filters['updated_since'] ?? null) {
            $query->where('updated_at', '>', Carbon::parse($since)->utc());
        }

        $mine = fn (Builder $spools) => $spools->where('user_id', $user->id);
        $owned = fn (Builder $spools) => $mine($spools)->active();

        match ((string) ($filters['owned'] ?? '')) {
            self::OWNED => $query->whereHas('spools', $owned),
            self::NOT_OWNED => $query->whereDoesntHave('spools', $owned),
            self::BOXED => $query->whereHas('spools', fn (Builder $spools) => $mine($spools)->where('status', SpoolStatus::Boxed)),
            self::ON_SPOOL => $query->whereHas('spools', fn (Builder $spools) => $mine($spools)->where('status', SpoolStatus::OnSpool)),
            // Running out: on the spool holder, but no boxed spool left as a backup.
            self::RUNNING_OUT => $query
                ->whereHas('spools', fn (Builder $spools) => $mine($spools)->where('status', SpoolStatus::OnSpool))
                ->whereDoesntHave('spools', fn (Builder $spools) => $mine($spools)->where('status', SpoolStatus::Boxed)),
            default => null,
        };

        return $query->orderBy('article_number');
    }

    /** @param  array<string, mixed>  $filters */
    public function paginate(User $user, array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        return $this->query($user, $filters)->paginate(max(1, min(self::MAX_PER_PAGE, $perPage)));
    }
}

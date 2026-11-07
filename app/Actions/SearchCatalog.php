<?php

namespace App\Actions;

use App\Models\FilamentProduct;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SearchCatalog
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param  array{q?: ?string, material?: ?string, line?: ?string, color?: ?string, brand?: ?string, owned?: ?string, updated_since?: ?string}  $filters
     *                                                                                                                                                       owned: "1" (owned), "0" (not owned), "low" (owned and low stock)
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

        $owned = fn (Builder $s) => $s->where('user_id', $user->id)->active();

        match ((string) ($filters['owned'] ?? '')) {
            '1' => $query->whereHas('spools', $owned),
            '0' => $query->whereDoesntHave('spools', $owned),
            'low' => $query->whereHas('spools', fn (Builder $s) => $s->where('user_id', $user->id)->lowStock()),
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

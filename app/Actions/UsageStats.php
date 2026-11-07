<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Models\FilamentProduct;
use App\Models\SpoolEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Spools added and used up per month, for all of a user's filament or for one product.
 * Removed spools (mistakes, given away) count as neither.
 */
class UsageStats
{
    /**
     * Oldest month first, including months without activity.
     *
     * @return list<array{month: string, label: string, added: int, used_up: int}>
     */
    public function monthly(User $user, int $months = 12, ?FilamentProduct $product = null): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $counts = $this->events($user, $product)
            ->where('spool_events.created_at', '>=', $start)
            ->get(['spool_events.type', 'spool_events.created_at'])
            ->groupBy(fn (SpoolEvent $event) => $event->created_at->format('Y-m'));

        return collect(range(0, $months - 1))->map(function (int $offset) use ($start, $counts) {
            $month = $start->copy()->addMonths($offset);
            $events = $counts->get($month->format('Y-m'), collect());

            return [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'added' => $events->where('type', SpoolEventType::Added)->count(),
                'used_up' => $events->where('type', SpoolEventType::UsedUp)->count(),
            ];
        })->all();
    }

    /**
     * The user's most used-up filaments.
     *
     * @return Collection<int, FilamentProduct> each with a used_up_count
     */
    public function topUsed(User $user, int $limit = 10): Collection
    {
        $usedUp = fn (Builder $spools) => $spools->withTrashed()
            ->where('user_id', $user->id)
            ->whereHas('events', fn (Builder $events) => $events->where('type', SpoolEventType::UsedUp));

        return FilamentProduct::query()
            ->with(['brand', 'material'])
            ->withCount(['spools as used_up_count' => $usedUp])
            ->whereHas('spools', $usedUp)
            ->orderByDesc('used_up_count')
            ->orderBy('article_number')
            ->limit($limit)
            ->get();
    }

    /** @return Builder<SpoolEvent> */
    private function events(User $user, ?FilamentProduct $product): Builder
    {
        return SpoolEvent::query()
            ->join('spools', 'spools.id', '=', 'spool_events.spool_id')
            ->where('spools.user_id', $user->id)
            ->when($product, fn (Builder $query) => $query->where('spools.filament_product_id', $product->id))
            ->whereIn('spool_events.type', [SpoolEventType::Added, SpoolEventType::UsedUp]);
    }
}

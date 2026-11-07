<?php

namespace App\Actions;

use App\Enums\SpoolStatus;
use App\Models\FilamentProduct;
use App\Models\Spool;
use App\Models\User;

/**
 * Inventory changes per catalog product, for the catalog list and the filament page:
 * the user works with counts ("2 boxed, 1 on spool") rather than individual spools.
 */
class AdjustInventory
{
    public function __construct(
        private AddSpool $addSpool,
        private OpenSpool $openSpool,
        private MarkSpoolUsedUp $markUsedUp,
        private RemoveSpool $removeSpool,
    ) {}

    /** Add one boxed spool. */
    public function add(User $user, FilamentProduct $product): Spool
    {
        return $this->addSpool->handle($user, ['filament_product_id' => $product->id]);
    }

    /** Put the oldest boxed spool on the spool holder. */
    public function open(User $user, FilamentProduct $product): ?Spool
    {
        $spool = $this->oldest($user, $product, SpoolStatus::Boxed);

        return $spool ? $this->openSpool->handle($spool) : null;
    }

    /** Mark the oldest spool on the holder as used up. */
    public function useUp(User $user, FilamentProduct $product): ?Spool
    {
        $spool = $this->oldest($user, $product, SpoolStatus::OnSpool);

        return $spool ? $this->markUsedUp->handle($spool) : null;
    }

    /** Remove the newest boxed spool, e.g. one added by mistake. */
    public function remove(User $user, FilamentProduct $product): bool
    {
        $spool = $user->spools()->where('filament_product_id', $product->id)
            ->where('status', SpoolStatus::Boxed)->latest('id')->first();

        if (! $spool) {
            return false;
        }

        $this->removeSpool->handle($spool);

        return true;
    }

    private function oldest(User $user, FilamentProduct $product, SpoolStatus $status): ?Spool
    {
        return $user->spools()->where('filament_product_id', $product->id)
            ->where('status', $status)->oldest('id')->first();
    }
}

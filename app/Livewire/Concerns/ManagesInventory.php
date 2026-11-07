<?php

namespace App\Livewire\Concerns;

use App\Actions\AdjustInventory;
use App\Models\FilamentProduct;
use Illuminate\Support\Facades\Auth;

/**
 * The buttons of <x-inventory-controls>: boxed and on-spool counts per catalog product.
 * Every action works on the signed-in user's own spools only.
 */
trait ManagesInventory
{
    /** Add one boxed spool. */
    public function addBoxed(int $productId, AdjustInventory $inventory): void
    {
        $inventory->add(Auth::user(), FilamentProduct::findOrFail($productId));
    }

    /** Remove a boxed spool that was added by mistake. */
    public function removeBoxed(int $productId, AdjustInventory $inventory): void
    {
        $inventory->remove(Auth::user(), FilamentProduct::findOrFail($productId));
    }

    /** Take a boxed spool out and put it on the spool holder. */
    public function putOnSpool(int $productId, AdjustInventory $inventory): void
    {
        $inventory->open(Auth::user(), FilamentProduct::findOrFail($productId));
    }

    /** Mark a spool on the holder as used up. */
    public function useUp(int $productId, AdjustInventory $inventory): void
    {
        $inventory->useUp(Auth::user(), FilamentProduct::findOrFail($productId));
    }
}

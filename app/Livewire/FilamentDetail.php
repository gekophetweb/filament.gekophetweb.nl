<?php

namespace App\Livewire;

use App\Actions\UsageStats;
use App\Livewire\Concerns\ManagesInventory;
use App\Models\FilamentProduct;
use App\Models\SpoolEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One catalog filament: the user's boxed / on-spool counts, buy link, usage graph and history. */
#[Layout('layouts.app')]
class FilamentDetail extends Component
{
    use ManagesInventory;

    public FilamentProduct $product;

    public function render(UsageStats $stats): View
    {
        $user = Auth::user();
        $product = FilamentProduct::query()->with(['brand', 'material'])->withOwnedCount($user)->findOrFail($this->product->id);

        return view('livewire.filament-detail', [
            'filament' => $product,
            'months' => $stats->monthly($user, 12, $product),
            'history' => SpoolEvent::query()
                ->whereHas('spool', fn ($spools) => $spools->where('user_id', $user->id)->where('filament_product_id', $product->id))
                ->with('spool')
                ->latest('created_at')->latest('id')
                ->limit(100)
                ->get(),
        ])->title($product->article_name);
    }
}

<?php

namespace App\Livewire;

use App\Models\Spool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SpoolDetail extends Component
{
    public Spool $spool;

    public function mount(Spool $spool): void
    {
        $this->authorize('view', $spool);
    }

    public function render(): View
    {
        $this->spool->load('product.brand', 'product.material');

        return view('livewire.spool-detail', [
            'events' => $this->spool->events()->limit(50)->get(),
        ])->title($this->spool->product->article_name);
    }
}

<?php

namespace App\Livewire;

use App\Actions\UpdateSpool;
use App\Models\Spool;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SpoolDetail extends Component
{
    public Spool $spool;

    public ?string $location = null;

    public ?string $notes = null;

    public function mount(Spool $spool): void
    {
        $this->authorize('view', $spool);
        $this->location = $spool->location;
        $this->notes = $spool->notes;
    }

    public function save(UpdateSpool $update): void
    {
        $this->authorize('update', $this->spool);
        $update->handle($this->spool, ['location' => $this->location, 'notes' => $this->notes]);
        session()->flash('saved', 'Saved.');
    }

    public function render(): View
    {
        $this->spool->load('product.brand', 'product.material');

        return view('livewire.spool-detail', [
            'events' => $this->spool->events()->limit(50)->get(),
        ])->title($this->spool->product->article_name);
    }
}

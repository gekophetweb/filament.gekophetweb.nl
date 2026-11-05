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

        // Oldest first for the chart.
        $points = $this->spool->events()->whereNotNull('weight')->limit(500)->get()->reverse()->values();

        return view('livewire.spool-detail', [
            'events' => $this->spool->events()->limit(50)->get(),
            'chart' => $this->chartPath($points->pluck('weight')->all()),
        ])->title($this->spool->product->article_name);
    }

    /**
     * SVG polyline points for a 600x160 weight-history chart (no JS library needed).
     *
     * @param  list<int>  $weights
     */
    private function chartPath(array $weights): ?string
    {
        if (count($weights) < 2) {
            return null;
        }

        $max = max($this->spool->initial_weight, ...$weights) ?: 1;
        $step = 600 / (count($weights) - 1);

        return collect($weights)
            ->map(fn (int $w, int $i) => round($i * $step, 1).','.round(160 - ($w / $max) * 150, 1))
            ->implode(' ');
    }
}

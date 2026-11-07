<?php

namespace App\Livewire;

use App\Actions\MarkSpoolUsedUp;
use App\Actions\OpenSpool;
use App\Enums\SpoolStatus;
use App\Models\FilamentProduct;
use App\Models\Material;
use App\Models\Spool;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My spools')]
class Spools extends Component
{
    use WithPagination;

    #[Url(except: 'active')]
    public string $status = 'active';

    /** Filament type (material name, e.g. PLA); empty for all. */
    #[Url(except: '')]
    public string $material = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedMaterial(): void
    {
        $this->resetPage();
    }

    public function putOnSpool(string $uuid, OpenSpool $open): void
    {
        $open->handle($this->find($uuid));
    }

    public function useUp(string $uuid, MarkSpoolUsedUp $usedUp): void
    {
        $usedUp->handle($this->find($uuid));
    }

    private function find(string $uuid): Spool
    {
        $spool = Spool::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $spool);

        return $spool;
    }

    public function render(): View
    {
        $user = Auth::user();
        $ofMaterial = fn (Builder $query) => $this->material === ''
            ? $query
            : $query->whereHas('product.material', fn (Builder $q) => $q->where('name', $this->material));

        $query = $ofMaterial($user->spools()->getQuery())->with('product.material', 'product.brand')
            ->orderBy(FilamentProduct::select('article_number')->whereColumn('filament_products.id', 'spools.filament_product_id'))
            ->orderBy('created_at')->orderBy('id');

        // Only spools you still have: used-up and archived spools never show here.
        $status = SpoolStatus::tryFrom($this->status);
        in_array($status, SpoolStatus::owned(), true)
            ? $query->where('status', $status->value)
            : $query->active();

        // Boxed and on-spool counts for the chosen filament type, whatever the status filter.
        $counts = $ofMaterial($user->spools()->getQuery())->active()
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.spools', [
            'spools' => $query->paginate(50),
            'statuses' => SpoolStatus::owned(),
            'materials' => Material::whereHas('products.spools', fn (Builder $q) => $q->whereBelongsTo($user))->orderBy('name')->pluck('name'),
            'boxedCount' => (int) ($counts[SpoolStatus::Boxed->value] ?? 0),
            'onSpoolCount' => (int) ($counts[SpoolStatus::OnSpool->value] ?? 0),
        ]);
    }
}

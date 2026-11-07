<?php

namespace App\Livewire;

use App\Actions\MarkSpoolUsedUp;
use App\Actions\OpenSpool;
use App\Enums\SpoolStatus;
use App\Models\Spool;
use Illuminate\Contracts\View\View;
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

    public function updatedStatus(): void
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
        $query = Auth::user()->spools()->with('product.material', 'product.brand')->orderByDesc('updated_at');

        match ($this->status) {
            'active' => $query->active(),
            'all' => null,
            default => $query->where('status', SpoolStatus::tryFrom($this->status)?->value ?? 'none'),
        };

        return view('livewire.spools', [
            'spools' => $query->paginate(50),
            'statuses' => SpoolStatus::cases(),
        ]);
    }
}

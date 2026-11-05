<?php

namespace App\Livewire;

use App\Actions\MarkSpoolEmpty;
use App\Actions\RecordWeight;
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

    /** @var array<string, string|int|null> uuid => grams typed in the quick-weigh box */
    public array $weights = [];

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function weigh(string $uuid, RecordWeight $record): void
    {
        $spool = $this->find($uuid);
        $this->validate(["weights.{$uuid}" => ['required', 'integer', 'min:0', 'max:20000']], [], ["weights.{$uuid}" => 'weight']);

        $record->handle($spool, (int) $this->weights[$uuid]);
        unset($this->weights[$uuid]);
    }

    public function markEmpty(string $uuid, MarkSpoolEmpty $empty): void
    {
        $empty->handle($this->find($uuid));
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
            'low' => $query->lowStock(),
            'all' => null,
            default => $query->where('status', SpoolStatus::tryFrom($this->status)?->value ?? 'none'),
        };

        return view('livewire.spools', [
            'spools' => $query->paginate(48),
            'statuses' => SpoolStatus::cases(),
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Actions\ImportSpools;
use App\Actions\MarkSpoolUsedUp;
use App\Actions\OpenSpool;
use App\Enums\SpoolStatus;
use App\Models\Spool;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My spools')]
class Spools extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(except: 'active')]
    public string $status = 'active';

    /** The CSV to import; at most 2 MB. */
    #[Validate(['required', 'file', 'mimes:csv,txt', 'max:2048'])]
    public ?TemporaryUploadedFile $importFile = null;

    /** @var array{created: int, updated: int, unchanged: int, errors: list<string>}|null */
    public ?array $importResult = null;

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

    public function import(ImportSpools $importer): void
    {
        $this->validate();

        try {
            $this->importResult = $importer->handle(Auth::user(), $this->importFile->getRealPath());
        } catch (InvalidArgumentException $e) {
            $this->addError('importFile', $e->getMessage());
        }

        $this->reset('importFile');
        $this->resetPage();
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
